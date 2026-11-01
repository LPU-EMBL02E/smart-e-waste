<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\AccountService;
use App\Modules\Identity\Support\AccountChangeRefused;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Every account rule and write. API_Design.md §7.6, P35, P36, P47,
 * System_Plan.md §4, web/docs/ACCOUNT-MANAGEMENT.md,
 * web/docs/TRACK-A-ACCOUNTS.md step A2.
 */
class AccountServiceTest extends TestCase
{
    use RefreshDatabase;

    private AccountService $accounts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accounts = app(AccountService::class);
    }

    public function test_it_registers_a_student_with_a_qr_credential(): void
    {
        $organization = Organization::factory()->create();

        $user = $this->accounts->register([
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'email' => 'ana@example.com',
            'student_number' => '2026000001',
            'organization_id' => $organization->id,
            'password' => 'correct-horse',
        ])->fresh();

        $this->assertTrue($user->isStudent());
        $this->assertTrue($user->isActive());
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertTrue(Hash::check('correct-horse', $user->password));
        $this->assertSame(config('ewaste.qr_token_length'), strlen($user->qrCredential->token));
    }

    public function test_it_ignores_a_role_status_or_confirmation_given_at_registration(): void
    {
        $user = $this->accounts->register([
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'email' => 'ana@example.com',
            'student_number' => '2026000001',
            'organization_id' => Organization::factory()->create()->id,
            'password' => 'correct-horse',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_INACTIVE,
            'email_verified_at' => now(),
        ])->fresh();

        $this->assertTrue($user->isStudent());
        $this->assertTrue($user->isActive());
        $this->assertFalse($user->hasVerifiedEmail());
    }

    public function test_it_creates_an_admin_made_account_as_confirmed_and_unactivated(): void
    {
        $user = $this->accounts->createByAdmin([
            'first_name' => 'Ben',
            'last_name' => 'Cruz',
            'email' => 'ben@example.com',
            'student_number' => null,
            'role' => User::ROLE_STUDENT,
            'organization_id' => Organization::factory()->create()->id,
            'status' => User::STATUS_ACTIVE,
        ])->fresh();

        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertFalse($user->isActivated());
        $this->assertNotNull($user->qrCredential);
    }

    public function test_it_refuses_to_disable_the_last_active_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertRefused(fn () => $this->accounts->update($admin, ['status' => User::STATUS_INACTIVE]));

        $this->assertTrue($admin->fresh()->isActive());
    }

    public function test_it_refuses_to_demote_the_last_active_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertRefused(fn () => $this->accounts->update($admin, [
            'role' => User::ROLE_STUDENT,
            'organization_id' => Organization::factory()->create()->id,
        ]));

        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_it_does_not_count_an_unactivated_admin_as_a_replacement(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->unactivated()->create();

        $this->assertRefused(fn () => $this->accounts->update($admin, ['status' => User::STATUS_INACTIVE]));
    }

    public function test_it_does_not_count_an_inactive_admin_as_a_replacement(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->inactive()->create();

        $this->assertRefused(fn () => $this->accounts->update($admin, ['status' => User::STATUS_INACTIVE]));
    }

    public function test_it_disables_an_admin_when_another_usable_admin_remains(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->create();

        $this->accounts->update($admin, ['status' => User::STATUS_INACTIVE]);

        $this->assertFalse($admin->fresh()->isActive());
    }

    public function test_it_disables_an_unactivated_admin_while_one_usable_admin_remains(): void
    {
        User::factory()->admin()->create();
        $unactivated = User::factory()->admin()->unactivated()->create();

        $this->accounts->update($unactivated, ['status' => User::STATUS_INACTIVE]);

        $this->assertFalse($unactivated->fresh()->isActive());
    }

    public function test_it_lets_the_last_admin_edit_their_own_name(): void
    {
        $admin = User::factory()->admin()->create();

        $this->accounts->update($admin, ['first_name' => 'Renamed']);

        $this->assertSame('Renamed', $admin->fresh()->first_name);
    }

    public function test_it_clears_the_organization_when_a_student_becomes_an_admin(): void
    {
        $student = User::factory()->create();

        $this->accounts->update($student, ['role' => User::ROLE_ADMIN]);

        $this->assertNull($student->fresh()->organization_id);
    }

    public function test_it_ends_sessions_and_links_when_an_account_is_disabled(): void
    {
        $student = User::factory()->create();
        $bystander = User::factory()->create();
        $this->addSession($student);
        $this->addSession($bystander);
        Password::broker()->createToken($student);
        Password::broker()->createToken($bystander);

        $this->accounts->update($student, ['status' => User::STATUS_INACTIVE]);

        $this->assertDatabaseMissing('sessions', ['user_id' => $student->id]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $student->email]);
        $this->assertDatabaseHas('sessions', ['user_id' => $bystander->id]);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $bystander->email]);
    }

    public function test_it_ends_sessions_when_the_email_changes(): void
    {
        $student = User::factory()->create();
        $this->addSession($student);

        $this->accounts->update($student, ['email' => 'new@example.com']);

        $this->assertDatabaseMissing('sessions', ['user_id' => $student->id]);
    }

    public function test_it_deletes_links_for_the_old_address_when_the_email_changes(): void
    {
        $student = User::factory()->create(['email' => 'old@example.com']);
        Password::broker()->createToken($student);

        $this->accounts->update($student, ['email' => 'new@example.com']);

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'old@example.com']);
    }

    public function test_it_keeps_the_email_confirmed_when_an_admin_changes_it(): void
    {
        $student = User::factory()->create();

        $this->accounts->update($student, ['email' => 'new@example.com']);

        $this->assertTrue($student->fresh()->hasVerifiedEmail());
    }

    public function test_it_ends_sessions_and_links_when_the_role_changes(): void
    {
        $student = User::factory()->create();
        $this->addSession($student);
        Password::broker()->createToken($student);

        $this->accounts->update($student, ['role' => User::ROLE_ADMIN]);

        $this->assertDatabaseMissing('sessions', ['user_id' => $student->id]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $student->email]);
    }

    public function test_it_keeps_sessions_and_links_when_only_the_name_changes(): void
    {
        $student = User::factory()->create();
        $this->addSession($student);
        Password::broker()->createToken($student);

        $this->accounts->update($student, ['first_name' => 'Renamed']);

        $this->assertDatabaseHas('sessions', ['user_id' => $student->id]);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $student->email]);
    }

    public function test_it_keeps_sessions_when_an_account_is_reactivated(): void
    {
        $student = User::factory()->inactive()->create();
        $this->addSession($student);

        $this->accounts->update($student, ['status' => User::STATUS_ACTIVE]);

        $this->assertDatabaseHas('sessions', ['user_id' => $student->id]);
    }

    public function test_it_sets_a_password_and_ends_sessions_and_links(): void
    {
        $student = User::factory()->unactivated()->create();
        $this->addSession($student);
        Password::broker()->createToken($student);

        $this->accounts->setPassword($student, 'correct-horse');

        $this->assertTrue(Hash::check('correct-horse', $student->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['user_id' => $student->id]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $student->email]);
    }

    public function test_it_regenerates_the_qr_token(): void
    {
        $student = User::factory()->create();
        $oldToken = $student->qrCredential->token;

        $credential = $this->accounts->regenerateQr($student);

        $this->assertNotSame($oldToken, $credential->token);
        $this->assertDatabaseMissing('user_qr_credentials', ['token' => $oldToken]);
        $this->assertDatabaseCount('user_qr_credentials', 1);
    }

    public function test_it_imports_students_as_confirmed_with_qr_credentials(): void
    {
        $organization = Organization::factory()->create(['code' => 'COE']);

        $errors = $this->accounts->import([
            2 => $this->row(['email' => 'one@example.com', 'student_number' => '1']),
            3 => $this->row(['email' => 'two@example.com', 'student_number' => '']),
        ]);

        $this->assertSame([], $errors);

        $user = User::where('email', 'one@example.com')->firstOrFail();
        $this->assertTrue($user->isStudent());
        $this->assertTrue($user->isActive());
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertFalse($user->isActivated());
        $this->assertSame($organization->id, $user->organization_id);
        $this->assertSame(config('ewaste.qr_token_length'), strlen($user->qrCredential->token));
        $this->assertNull(User::where('email', 'two@example.com')->value('student_number'));
    }

    public function test_it_imports_all_rows_or_none(): void
    {
        Organization::factory()->create(['code' => 'COE']);
        $usersBefore = User::count();

        $errors = $this->accounts->import([
            2 => $this->row(['email' => 'good@example.com']),
            3 => $this->row(['email' => 'not-an-email', 'student_number' => '2']),
        ]);

        $this->assertSame([3], array_keys($errors));
        $this->assertSame($usersBefore, User::count());
    }

    public function test_it_reports_every_error_by_line_number(): void
    {
        Organization::factory()->create(['code' => 'COE']);

        $errors = $this->accounts->import([
            7 => $this->row(['first_name' => '', 'email' => 'bad', 'organization_code' => 'NOPE']),
        ]);

        $this->assertCount(3, $errors[7]);
    }

    public function test_it_refuses_an_email_or_student_number_already_taken(): void
    {
        Organization::factory()->create(['code' => 'COE']);
        User::factory()->create(['email' => 'taken@example.com', 'student_number' => 'S1']);

        $errors = $this->accounts->import([
            2 => $this->row(['email' => 'taken@example.com', 'student_number' => 'new']),
            3 => $this->row(['email' => 'new@example.com', 'student_number' => 'S1']),
        ]);

        $this->assertSame([2, 3], array_keys($errors));
    }

    public function test_it_refuses_duplicates_inside_the_file(): void
    {
        Organization::factory()->create(['code' => 'COE']);

        $errors = $this->accounts->import([
            2 => $this->row(['email' => 'same@example.com', 'student_number' => 'A']),
            3 => $this->row(['email' => 'SAME@example.com', 'student_number' => 'B']),
            4 => $this->row(['email' => 'other@example.com', 'student_number' => 'a']),
        ]);

        $this->assertSame([3, 4], array_keys($errors));
    }

    public function test_it_allows_several_blank_student_numbers(): void
    {
        Organization::factory()->create(['code' => 'COE']);

        $errors = $this->accounts->import([
            2 => $this->row(['email' => 'one@example.com', 'student_number' => '']),
            3 => $this->row(['email' => 'two@example.com', 'student_number' => '  ']),
        ]);

        $this->assertSame([], $errors);
    }

    public function test_it_refuses_an_import_into_an_inactive_organization(): void
    {
        Organization::factory()->inactive()->create(['code' => 'OLD']);

        $errors = $this->accounts->import([
            2 => $this->row(['organization_code' => 'OLD']),
        ]);

        $this->assertSame([2], array_keys($errors));
        $this->assertDatabaseMissing('users', ['email' => 'student@example.com']);
    }

    public function test_it_sends_no_email_on_import(): void
    {
        Mail::fake();
        Notification::fake();
        Organization::factory()->create(['code' => 'COE']);

        $this->accounts->import([2 => $this->row()]);

        Mail::assertNothingSent();
        Notification::assertNothingSent();
    }

    public function test_it_imports_a_large_file_without_a_query_per_row(): void
    {
        Organization::factory()->create(['code' => 'COE']);

        $rows = [];
        foreach (range(1, 1001) as $number) {
            $rows[$number + 1] = $this->row(['email' => "s{$number}@example.com", 'student_number' => "S{$number}"]);
        }

        DB::enableQueryLog();
        $errors = $this->accounts->import($rows);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame([], $errors);
        $this->assertSame(1001, User::count());
        $this->assertDatabaseCount('user_qr_credentials', 1001);
        $this->assertLessThan(30, $queries);
    }

    private function assertRefused(callable $change): void
    {
        $refused = false;

        try {
            $change();
        } catch (AccountChangeRefused) {
            $refused = true;
        }

        $this->assertTrue($refused, 'The change should have been refused.');
    }

    private function addSession(User $user): void
    {
        DB::table('sessions')->insert([
            'id' => "session-{$user->id}",
            'user_id' => $user->id,
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);
    }

    private function row(array $overrides = []): array
    {
        return [
            'student_number' => '2026000001',
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'email' => 'student@example.com',
            'organization_code' => 'COE',
            ...$overrides,
        ];
    }
}
