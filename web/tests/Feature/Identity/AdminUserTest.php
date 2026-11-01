<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\AccountChangeRefused;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

/**
 * The admin user pages. API_Design.md §7.1, §7.4, §7.6, P33, P36, P41, P47,
 * web/docs/ACCOUNT-MANAGEMENT.md, web/docs/TRACK-A-ACCOUNTS.md step A5.
 */
class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private const CSV_HEADER = "student_number,first_name,last_name,email,organization_code\n";

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_it_lets_only_admins_reach_the_user_pages(): void
    {
        $user = User::factory()->create();
        $requests = [
            ['get', route('admin.users.index')],
            ['get', route('admin.users.create')],
            ['post', route('admin.users.store')],
            ['get', route('admin.users.edit', $user)],
            ['put', route('admin.users.update', $user)],
            ['post', route('admin.users.import')],
            ['post', route('admin.users.password-link', $user)],
            ['put', route('admin.users.password', $user)],
        ];

        foreach ($requests as [$method, $url]) {
            $this->{$method}($url)->assertRedirect(route('login'));
        }

        $this->actingAs($user);

        foreach ($requests as [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }
    }

    public function test_it_lists_users_and_shows_which_are_not_activated_or_confirmed(): void
    {
        User::factory()->unactivated()->create(['first_name' => 'Paolo']);
        User::factory()->unconfirmed()->create(['first_name' => 'Liza']);

        $this->actingAs($this->admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Paolo')
            ->assertSee('Not activated')
            ->assertSee('Liza')
            ->assertSee('Not confirmed');
    }

    public function test_it_paginates_the_user_list(): void
    {
        User::factory()->count(16)->create();

        $response = $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk();

        $this->assertCount(15, $response->viewData('users'));
        $this->assertSame(17, $response->viewData('users')->total());
    }

    public function test_it_lists_users_without_a_query_per_row(): void
    {
        $this->actingAs($this->admin);
        User::factory()->count(2)->create();
        $few = $this->countQueries(fn () => $this->get(route('admin.users.index'))->assertOk());

        User::factory()->count(8)->create();
        $more = $this->countQueries(fn () => $this->get(route('admin.users.index'))->assertOk());

        $this->assertSame($few, $more);
    }

    public function test_it_offers_only_active_organizations_for_a_new_user(): void
    {
        Organization::factory()->create(['name' => 'Open College']);
        Organization::factory()->inactive()->create(['name' => 'Closed College']);

        $this->actingAs($this->admin)
            ->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('Open College')
            ->assertDontSee('Closed College');
    }

    public function test_it_creates_a_user_and_sends_the_set_password_link(): void
    {
        Notification::fake();

        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), $this->form())
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status');

        $user = User::where('email', 'paolo@example.com')->firstOrFail();
        $this->assertTrue($user->isStudent());
        $this->assertFalse($user->isActivated());
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertNotNull($user->qrCredential);
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_it_keeps_the_user_when_the_link_email_fails(): void
    {
        Exceptions::fake();
        Notification::shouldReceive('send')->andThrow(new RuntimeException('mail is down'));

        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), $this->form())
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'was not sent'));

        $this->assertDatabaseHas('users', ['email' => 'paolo@example.com']);
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_it_sends_no_link_to_a_new_inactive_user(): void
    {
        Notification::fake();

        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), $this->form(['status' => User::STATUS_INACTIVE]))
            ->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'was not sent'));

        Notification::assertNothingSent();
    }

    public function test_it_creates_an_admin_with_no_organization(): void
    {
        Notification::fake();

        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), $this->form(['role' => User::ROLE_ADMIN, 'organization_id' => '']))
            ->assertSessionHasNoErrors();

        $user = User::where('email', 'paolo@example.com')->firstOrFail();
        $this->assertTrue($user->isAdmin());
        $this->assertNull($user->organization_id);
    }

    public function test_it_requires_an_organization_for_a_student(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), $this->form(['organization_id' => '']))
            ->assertSessionHasErrors('organization_id');
    }

    public function test_it_refuses_an_organization_for_an_admin(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), $this->form(['role' => User::ROLE_ADMIN]))
            ->assertSessionHasErrors('organization_id');
    }

    public function test_it_refuses_a_new_student_in_an_inactive_organization(): void
    {
        $organization = Organization::factory()->inactive()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), $this->form(['organization_id' => $organization->id]))
            ->assertSessionHasErrors('organization_id');
    }

    public function test_it_shows_the_edit_form_with_the_users_inactive_organization(): void
    {
        $organization = Organization::factory()->inactive()->create(['name' => 'Closed College']);
        $user = User::factory()->for($organization)->create();

        $this->actingAs($this->admin)
            ->get(route('admin.users.edit', $user))
            ->assertOk()
            ->assertSee('Closed College');
    }

    public function test_it_updates_a_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $user), $this->form([
                'first_name' => 'Renamed',
                'organization_id' => $user->organization_id,
            ]))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $user->fresh()->first_name);
    }

    public function test_it_ends_the_sessions_of_a_disabled_user(): void
    {
        $user = User::factory()->create();
        DB::table('sessions')->insert([
            'id' => 'student-session',
            'user_id' => $user->id,
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);

        $this->actingAs($this->admin)->put(route('admin.users.update', $user), $this->form([
            'status' => User::STATUS_INACTIVE,
            'organization_id' => $user->organization_id,
        ]));

        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
    }

    public function test_it_refuses_to_disable_the_last_admin_and_says_why(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.users.edit', $this->admin))
            ->put(route('admin.users.update', $this->admin), $this->form([
                'email' => $this->admin->email,
                'role' => User::ROLE_ADMIN,
                'organization_id' => '',
                'status' => User::STATUS_INACTIVE,
            ]))
            ->assertRedirect(route('admin.users.edit', $this->admin))
            ->assertSessionHasErrors(['status' => AccountChangeRefused::lastAdmin()->getMessage()]);

        $this->assertTrue($this->admin->fresh()->isActive());
    }

    public function test_it_sends_a_link_at_once_even_straight_after_another(): void
    {
        Notification::fake();
        $user = User::factory()->unactivated()->create();
        $this->actingAs($this->admin);

        foreach (range(1, 2) as $click) {
            $this->from(route('admin.users.index'))
                ->post(route('admin.users.password-link', $user))
                ->assertRedirect(route('admin.users.index'))
                ->assertSessionHas('status', fn (string $status): bool => str_contains($status, $user->email));
        }

        Notification::assertSentToTimes($user, ResetPassword::class, 2);
    }

    public function test_it_keeps_only_the_latest_link(): void
    {
        Notification::fake();
        $user = User::factory()->unactivated()->create();
        $earlier = Password::broker()->createToken($user);

        $this->actingAs($this->admin)->post(route('admin.users.password-link', $user));

        $this->assertFalse(Password::broker()->tokenExists($user, $earlier));
        $this->assertDatabaseCount('password_reset_tokens', 1);
    }

    public function test_it_sends_no_link_to_an_inactive_user(): void
    {
        Notification::fake();
        $user = User::factory()->inactive()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.users.password-link', $user))
            ->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'could not be sent'));

        Notification::assertNothingSent();
    }

    public function test_it_says_so_when_a_link_email_fails(): void
    {
        Exceptions::fake();
        $user = User::factory()->unactivated()->create();
        Notification::shouldReceive('send')->andThrow(new RuntimeException('mail is down'));

        $this->actingAs($this->admin)
            ->post(route('admin.users.password-link', $user))
            ->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'could not be sent'));

        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_it_sets_a_password_as_the_fallback(): void
    {
        $user = User::factory()->unactivated()->create();

        $this->actingAs($this->admin)
            ->from(route('admin.users.edit', $user))
            ->put(route('admin.users.password', $user), [
                'password' => 'correct-horse',
                'password_confirmation' => 'correct-horse',
            ])
            ->assertRedirect(route('admin.users.edit', $user))
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('correct-horse', $user->fresh()->password));
    }

    public function test_it_requires_a_confirmed_fallback_password_of_at_least_eight_characters(): void
    {
        $user = User::factory()->unactivated()->create();
        $this->actingAs($this->admin);

        $this->put(route('admin.users.password', $user), ['password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors('password');
        $this->put(route('admin.users.password', $user), ['password' => 'correct-horse', 'password_confirmation' => 'other'])
            ->assertSessionHasErrors('password');

        $this->assertFalse($user->fresh()->isActivated());
    }

    public function test_it_imports_a_csv_and_sends_no_email(): void
    {
        Notification::fake();
        Organization::factory()->create(['code' => 'COE']);

        $this->import(self::CSV_HEADER."2024-1,Ana,Lim,ana@example.com,COE\n2024-2,Ben,Cruz,ben@example.com,COE\n")
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'Imported 2 students'));

        $this->assertDatabaseHas('users', ['email' => 'ana@example.com', 'student_number' => '2024-1']);
        $this->assertDatabaseHas('users', ['email' => 'ben@example.com']);
        Notification::assertNothingSent();
    }

    public function test_it_reports_csv_row_errors_and_imports_nothing(): void
    {
        Organization::factory()->create(['code' => 'COE']);
        $before = User::count();

        $this->import(self::CSV_HEADER."2024-1,Ana,Lim,ana@example.com,COE\n2024-2,Ben,Cruz,not-an-email,CEA\n")
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('import_errors', fn (array $errors): bool => array_keys($errors) === [3] && count($errors[3]) === 2);

        $this->assertSame($before, User::count());

        $this->get(route('admin.users.index'))->assertSee('Nothing was imported')->assertSee('Row 3:');
    }

    public function test_it_reads_a_csv_with_a_byte_order_mark_and_columns_in_any_order(): void
    {
        Organization::factory()->create(['code' => 'COE']);

        $this->import("\xEF\xBB\xBF"."EMAIL, first_name,last_name,organization_code,student_number\nana@example.com,Ana,Lim,COE,\n")
            ->assertSessionHas('status');

        $this->assertDatabaseHas('users', ['email' => 'ana@example.com', 'first_name' => 'Ana', 'student_number' => null]);
    }

    public function test_it_reads_a_csv_with_windows_line_endings(): void
    {
        Organization::factory()->create(['code' => 'COE']);

        $this->import("student_number,first_name,last_name,email,organization_code\r\n2024-1,Ana,Lim,ana@example.com,COE\r\n")
            ->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'Imported 1 student.'));

        $this->assertDatabaseHas('users', ['email' => 'ana@example.com', 'organization_id' => Organization::first()->id]);
    }

    public function test_it_keeps_names_with_commas_and_accents_from_a_csv(): void
    {
        Organization::factory()->create(['code' => 'COE']);

        $this->import(self::CSV_HEADER."2024-1,\"Maria, Jr.\",Peña,ana@example.com,COE\n")->assertSessionHas('status');

        $this->assertDatabaseHas('users', ['first_name' => 'Maria, Jr.', 'last_name' => 'Peña']);
    }

    public function test_it_skips_blank_lines_but_keeps_counting_them_in_a_csv(): void
    {
        Organization::factory()->create(['code' => 'COE']);

        $this->import(self::CSV_HEADER."\n2024-1,Ana,Lim,bad-email,COE\n\n")
            ->assertSessionHas('import_errors', fn (array $errors): bool => array_keys($errors) === [3]);
    }

    public function test_it_refuses_a_csv_with_the_wrong_columns(): void
    {
        $before = User::count();

        $this->import("number,name,email\n1,Ana,ana@example.com\n")
            ->assertSessionHas('import_errors', fn (array $errors): bool => array_keys($errors) === [1]);

        $this->assertSame($before, User::count());
    }

    public function test_it_refuses_a_csv_row_with_the_wrong_number_of_columns_and_imports_nothing(): void
    {
        Organization::factory()->create(['code' => 'COE']);
        $before = User::count();

        $this->import(self::CSV_HEADER."2024-1,Ana,Lim,ana@example.com,COE\n2024-2,Ben\n")
            ->assertSessionHas('import_errors', fn (array $errors): bool => array_keys($errors) === [3]);

        $this->assertSame($before, User::count());
    }

    public function test_it_refuses_a_csv_row_that_is_not_utf8_and_imports_nothing(): void
    {
        Organization::factory()->create(['code' => 'COE']);
        $before = User::count();

        $this->import(self::CSV_HEADER."2024-1,Ana,Pe\xF1a,ana@example.com,COE\n")
            ->assertSessionHas('import_errors', fn (array $errors): bool => array_keys($errors) === [2]);

        $this->assertSame($before, User::count());
    }

    public function test_it_refuses_a_csv_with_no_students(): void
    {
        $this->import(self::CSV_HEADER)
            ->assertSessionHas('import_errors', fn (array $errors): bool => array_keys($errors) === [1]);
    }

    public function test_it_requires_a_csv_file(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.import'), [])
            ->assertSessionHasErrors('file');
    }

    private function import(string $csv): TestResponse
    {
        return $this->actingAs($this->admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.users.import'), [
                'file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
            ]);
    }

    private function form(array $overrides = []): array
    {
        return [
            'first_name' => 'Paolo',
            'last_name' => 'Reyes',
            'email' => 'paolo@example.com',
            'student_number' => '2024-00456',
            'role' => User::ROLE_STUDENT,
            'organization_id' => array_key_exists('organization_id', $overrides)
                ? $overrides['organization_id']
                : Organization::factory()->create()->id,
            'status' => User::STATUS_ACTIVE,
            ...$overrides,
        ];
    }

    private function countQueries(callable $request): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $request();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
