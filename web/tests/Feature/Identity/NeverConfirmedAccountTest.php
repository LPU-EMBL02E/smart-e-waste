<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\AccountService;
use App\Modules\Notifications\Models\Notification as InAppNotification;
use App\Modules\Rewards\Models\PointLedgerEntry;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Accounts never confirmed are deleted a week after registration, and the user
 * is told by when. API_Design.md §7.2, P54; Database_Schema.md §2
 * (users.first_confirmed_at).
 */
class NeverConfirmedAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_an_account_never_confirmed_after_a_week_with_its_rows(): void
    {
        $user = $this->neverConfirmed(daysAgo: 8);
        DB::table('sessions')->insert(['id' => 'its-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => 0]);
        DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'x', 'created_at' => now()]);

        $this->assertSame(1, $this->sweep());

        $this->assertModelMissing($user);
        $this->assertDatabaseMissing('user_qr_credentials', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_it_frees_the_email_and_student_number_to_register_again(): void
    {
        $user = $this->neverConfirmed(daysAgo: 8);
        $this->sweep();

        $this->post(route('register'), [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => $user->email,
            'student_number' => $user->student_number,
            'organization_id' => $user->organization_id,
            'password' => 'correct-horse',
            'password_confirmation' => 'correct-horse',
        ])->assertSessionHasNoErrors()->assertRedirect(route('verification.notice'));
    }

    public function test_it_keeps_an_account_never_confirmed_within_the_week(): void
    {
        $user = $this->neverConfirmed(daysAgo: 6);

        $this->assertSame(0, $this->sweep());

        $this->assertModelExists($user);
    }

    public function test_it_keeps_an_account_confirmed_before_that_is_confirming_a_changed_email(): void
    {
        $user = User::factory()->reconfirming()->create(['created_at' => now()->subYear()]);

        $this->assertSame(0, $this->sweep());

        $this->assertModelExists($user);
    }

    public function test_it_keeps_an_account_never_confirmed_that_has_points_or_notifications(): void
    {
        $withPoints = $this->neverConfirmed(daysAgo: 8);
        PointLedgerEntry::factory()->for($withPoints)->create();
        $withNotification = $this->neverConfirmed(daysAgo: 8);
        InAppNotification::factory()->for($withNotification)->create();

        $this->assertSame(0, $this->sweep());

        $this->assertModelExists($withPoints);
        $this->assertModelExists($withNotification);
    }

    public function test_it_runs_the_sweep_every_hour(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->firstWhere('description', 'accounts:delete-never-confirmed');

        $this->assertNotNull($event);
        $this->assertSame('0 * * * *', $event->expression);
    }

    public function test_it_keeps_the_first_confirmation_for_good(): void
    {
        $user = User::factory()->unconfirmed()->create();
        Carbon::setTestNow('2026-11-22 01:00:00');
        $this->actingAs($user)->get($this->confirmationLink($user));
        $firstConfirmedAt = $user->fresh()->first_confirmed_at;
        $this->assertTrue($firstConfirmedAt->equalTo('2026-11-22 01:00:00'));

        $user->forceFill(['email' => 'new@example.com', 'email_verified_at' => null])->save();
        Carbon::setTestNow('2026-12-01 01:00:00');
        $this->actingAs($user)->get($this->confirmationLink($user));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertTrue($user->fresh()->first_confirmed_at->equalTo($firstConfirmedAt));
    }

    public function test_it_counts_an_account_an_admin_creates_as_confirmed_before(): void
    {
        $user = app(AccountService::class)->createByAdmin([
            'role' => User::ROLE_ADMIN,
            'email' => 'ana@example.com',
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
        ]);

        $this->assertNotNull($user->fresh()->first_confirmed_at);
        $this->assertNull($user->confirmationDeadline());
    }

    public function test_it_shows_the_deadline_on_the_confirmation_page(): void
    {
        $user = User::factory()->unconfirmed()->create(['created_at' => '2026-11-22 07:00:00']);

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertSee('Confirm it by')
            ->assertSee('29 Nov 2026, 3:00pm (Asia/Manila)')
            ->assertSee('you can sign up again');
    }

    public function test_it_shows_no_deadline_when_confirming_a_changed_email(): void
    {
        $this->actingAs(User::factory()->reconfirming()->create())
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertDontSee('Confirm it by');
    }

    public function test_it_puts_the_deadline_in_the_confirmation_email(): void
    {
        $user = User::factory()->unconfirmed()->create(['created_at' => '2026-11-22 07:00:00']);

        $mail = (new VerifyEmail)->toMail($user);

        $this->assertNotNull($mail->actionUrl);
        $this->assertContains(
            'Confirm it by 29 Nov 2026, 3:00pm (Asia/Manila). After that, the account is deleted and the email address can be used to sign up again.',
            $mail->outroLines,
        );
    }

    public function test_it_leaves_the_deadline_out_of_the_email_for_a_changed_address(): void
    {
        $user = User::factory()->reconfirming()->create();

        $mail = (new VerifyEmail)->toMail($user);

        $this->assertSame(['If you did not create an account, no further action is required.'], $mail->outroLines);
    }

    private function neverConfirmed(int $daysAgo): User
    {
        return User::factory()->unconfirmed()->create(['created_at' => now()->subDays($daysAgo)]);
    }

    private function sweep(): int
    {
        return app(AccountService::class)->deleteNeverConfirmedAccounts();
    }

    private function confirmationLink(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);
    }
}
