<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\User;
use Database\Seeders\FirstAdminSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

/**
 * Password links and the first admin. API_Design.md §7.2, P30, P41, P47,
 * System_Plan.md §4 and §7, web/docs/ACCOUNT-MANAGEMENT.md,
 * web/docs/TRACK-A-ACCOUNTS.md step A3.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_PASSWORD = 'correct-horse';

    public function test_it_shows_the_forgot_password_form(): void
    {
        $this->get(route('password.request'))->assertOk()->assertSee('Forgot password');
    }

    public function test_it_gives_the_same_forgot_password_answer_for_any_email(): void
    {
        Notification::fake();
        $active = User::factory()->create();
        $inactive = User::factory()->inactive()->create();

        $answers = array_map(
            fn (string $email): ?string => $this->forgot($email)->baseResponse->getSession()->get('status'),
            [$active->email, $inactive->email, 'nobody@example.com'],
        );

        $this->assertNotNull($answers[0]);
        $this->assertSame([$answers[0]], array_values(array_unique($answers)));
    }

    public function test_it_sends_a_link_to_an_active_account(): void
    {
        Notification::fake();
        $student = User::factory()->create();

        $this->forgot($student->email);

        Notification::assertSentTo($student, ResetPassword::class);
    }

    public function test_it_sends_a_link_to_an_account_with_no_password_yet(): void
    {
        Notification::fake();
        $student = User::factory()->unactivated()->create();

        $this->forgot($student->email);

        Notification::assertSentTo($student, ResetPassword::class);
    }

    public function test_it_sends_no_link_to_an_inactive_account(): void
    {
        Notification::fake();
        $student = User::factory()->inactive()->create();

        $this->forgot($student->email);

        Notification::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $student->email]);
    }

    public function test_it_gives_the_same_answer_and_logs_it_when_the_send_fails(): void
    {
        Exceptions::fake();
        $student = User::factory()->create();
        Notification::shouldReceive('send')->andThrow(new RuntimeException('mail is down'));

        $response = $this->forgot('nobody@example.com');
        $expected = $response->baseResponse->getSession()->get('status');

        $this->forgot($student->email)->assertSessionHas('status', $expected);

        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_it_requires_a_valid_email_to_send_a_link(): void
    {
        $this->forgot('not-an-email')->assertSessionHasErrors('email');
    }

    public function test_it_shows_the_reset_form_with_the_token_and_email(): void
    {
        $this->get(route('password.reset', ['token' => 'the-token', 'email' => 'ana@example.com']))
            ->assertOk()
            ->assertSee('the-token')
            ->assertSee('ana@example.com');
    }

    public function test_it_sets_a_password_from_a_valid_link(): void
    {
        $student = User::factory()->unactivated()->create();
        $token = Password::broker()->createToken($student);

        $this->reset($student->email, $token)
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $student->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $student->email]);
    }

    public function test_it_ends_the_users_sessions_after_a_reset(): void
    {
        $student = User::factory()->create();
        DB::table('sessions')->insert([
            'id' => 'old-session',
            'user_id' => $student->id,
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);

        $this->reset($student->email, Password::broker()->createToken($student));

        $this->assertDatabaseMissing('sessions', ['user_id' => $student->id]);
    }

    public function test_it_refuses_a_wrong_token(): void
    {
        $student = User::factory()->create();
        Password::broker()->createToken($student);
        $passwordBefore = $student->password;

        $this->reset($student->email, 'wrong-token')->assertSessionHasErrors('email');

        $this->assertSame($passwordBefore, $student->fresh()->password);
    }

    public function test_it_refuses_a_link_used_twice(): void
    {
        $student = User::factory()->create();
        $token = Password::broker()->createToken($student);

        $this->reset($student->email, $token)->assertSessionHasNoErrors();

        $this->reset($student->email, $token)->assertSessionHasErrors('email');
    }

    public function test_it_refuses_an_expired_link(): void
    {
        $student = User::factory()->create();
        $token = Password::broker()->createToken($student);

        $this->travel(config('auth.passwords.users.expire') + 1)->minutes();

        $this->reset($student->email, $token)->assertSessionHasErrors('email');
    }

    public function test_it_refuses_an_earlier_link_after_a_new_one_is_sent(): void
    {
        $student = User::factory()->create();
        $earlier = Password::broker()->createToken($student);
        Password::broker()->createToken($student);

        $this->reset($student->email, $earlier)->assertSessionHasErrors('email');
    }

    public function test_it_refuses_a_link_for_an_inactive_account_with_the_same_message(): void
    {
        $message = 'This link is not valid or has expired. Ask for a new one.';

        $student = User::factory()->create();
        $this->reset($student->email, 'wrong-token')->assertSessionHasErrors(['email' => $message]);

        $inactive = User::factory()->inactive()->create();
        $passwordBefore = $inactive->password;
        $token = Password::broker()->createToken($inactive);

        $this->reset($inactive->email, $token)->assertSessionHasErrors(['email' => $message]);

        $this->assertSame($passwordBefore, $inactive->fresh()->password);
    }

    public function test_it_requires_a_confirmed_password_of_at_least_eight_characters(): void
    {
        $student = User::factory()->create();
        $token = Password::broker()->createToken($student);

        $this->reset($student->email, $token, 'short', 'short')->assertSessionHasErrors('password');
        $this->reset($student->email, $token, self::NEW_PASSWORD, 'different')->assertSessionHasErrors('password');
    }

    public function test_it_seeds_one_unactivated_admin(): void
    {
        config(['ewaste.first_admin.email' => 'admin@example.com']);

        $this->seed(FirstAdminSeeder::class);

        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->assertSame(1, User::count());
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->isActive());
        $this->assertFalse($admin->isActivated());
        $this->assertTrue($admin->hasVerifiedEmail());
        $this->assertNull($admin->organization_id);
        $this->assertNotNull($admin->qrCredential);
    }

    public function test_it_seeds_no_second_admin(): void
    {
        config(['ewaste.first_admin.email' => 'admin@example.com']);
        User::factory()->admin()->create();

        $this->seed(FirstAdminSeeder::class);

        $this->assertSame(1, User::count());
    }

    public function test_it_refuses_to_seed_without_an_email(): void
    {
        config(['ewaste.first_admin.email' => '']);

        $this->expectException(RuntimeException::class);

        $this->seed(FirstAdminSeeder::class);
    }

    public function test_it_activates_the_first_admin_through_a_password_link(): void
    {
        Notification::fake();
        config(['ewaste.first_admin.email' => 'admin@example.com']);
        $this->seed(FirstAdminSeeder::class);
        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $this->from(route('login'))
            ->post(route('login'), ['email' => $admin->email, 'password' => self::NEW_PASSWORD])
            ->assertSessionHasErrors('email');

        $this->forgot($admin->email);

        $token = null;
        Notification::assertSentTo($admin, ResetPassword::class, function (ResetPassword $mail) use (&$token): bool {
            $token = $mail->token;

            return true;
        });

        $this->reset($admin->email, $token)->assertRedirect(route('login'));

        $this->from(route('login'))
            ->post(route('login'), ['email' => $admin->email, 'password' => self::NEW_PASSWORD])
            ->assertRedirect(route('admin.dashboard'));

        $this->get(route('admin.dashboard'))->assertOk();
    }

    private function forgot(string $email): TestResponse
    {
        return $this->from(route('password.request'))->post(route('password.email'), ['email' => $email]);
    }

    private function reset(
        string $email,
        string $token,
        string $password = self::NEW_PASSWORD,
        string $confirmation = self::NEW_PASSWORD,
    ): TestResponse {
        return $this->from(route('login'))->post(route('password.update'), [
            'email' => $email,
            'token' => $token,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ]);
    }
}
