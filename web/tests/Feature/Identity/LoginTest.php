<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\LoginRefused;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Signing in and out. API_Design.md §7.1–§7.3, P6, P29,
 * web/docs/TRACK-A-ACCOUNTS.md step A3.
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_the_login_form(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Sign in');
    }

    public function test_it_signs_in_a_student_and_redirects_to_home(): void
    {
        $student = User::factory()->create();

        $this->attempt($student->email)->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($student);
    }

    public function test_it_signs_in_an_admin_and_redirects_to_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->attempt($admin->email)->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_it_rejects_a_wrong_password(): void
    {
        $student = User::factory()->create();

        $this->attempt($student->email, 'wrong-password')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => $this->genericMessage()]);

        $this->assertGuest();
    }

    public function test_it_rejects_an_unknown_email_with_the_generic_message(): void
    {
        $this->attempt('nobody@example.com')
            ->assertSessionHasErrors(['email' => $this->genericMessage()]);

        $this->assertGuest();
    }

    public function test_it_rejects_an_unactivated_account_with_the_generic_message(): void
    {
        $student = User::factory()->unactivated()->create();

        $this->attempt($student->email)
            ->assertSessionHasErrors(['email' => $this->genericMessage()]);

        $this->assertGuest();
    }

    public function test_it_rejects_an_inactive_account_with_the_generic_message(): void
    {
        $student = User::factory()->inactive()->create();

        $this->attempt($student->email)
            ->assertSessionHasErrors(['email' => $this->genericMessage()]);

        $this->assertGuest();
    }

    public function test_it_keeps_the_email_but_never_the_password_after_a_failure(): void
    {
        $this->attempt('nobody@example.com');

        $this->assertSame('nobody@example.com', session()->getOldInput('email'));
        $this->assertNull(session()->getOldInput('password'));
    }

    public function test_it_limits_login_to_five_attempts_a_minute(): void
    {
        $this->freezeTime();
        $student = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->attempt($student->email, 'wrong-password')
                ->assertSessionHasErrors(['email' => $this->genericMessage()]);
        }

        // Refused even with the right password.
        $seconds = config('ewaste.login.decay_seconds');
        $this->attempt($student->email)->assertSessionHasErrors([
            'email' => LoginRefused::tooManyAttempts($seconds)->getMessage(),
        ]);

        $this->assertGuest();
    }

    public function test_it_lets_a_user_sign_in_again_after_the_minute_passes(): void
    {
        $student = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->attempt($student->email, 'wrong-password');
        }

        $this->travel(config('ewaste.login.decay_seconds') + 1)->seconds();

        $this->attempt($student->email)->assertRedirect(route('home'));
    }

    public function test_it_limits_by_email_and_ip_together(): void
    {
        $student = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->attempt($student->email, 'wrong-password');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->attempt($student->email)
            ->assertRedirect(route('home'));
    }

    public function test_it_clears_failed_attempts_after_a_success(): void
    {
        $student = User::factory()->create();

        foreach (range(1, 4) as $attempt) {
            $this->attempt($student->email, 'wrong-password');
        }

        $this->attempt($student->email)->assertRedirect(route('home'));
        $this->post(route('logout'));

        foreach (range(1, 4) as $attempt) {
            $this->attempt($student->email, 'wrong-password')
                ->assertSessionHasErrors(['email' => $this->genericMessage()]);
        }
    }

    public function test_it_sets_last_login_at(): void
    {
        $student = User::factory()->create(['last_login_at' => null]);

        $this->attempt($student->email);

        $this->assertNotNull($student->fresh()->last_login_at);
    }

    public function test_it_leaves_last_login_at_alone_after_a_failure(): void
    {
        $student = User::factory()->create(['last_login_at' => null]);

        $this->attempt($student->email, 'wrong-password');

        $this->assertNull($student->fresh()->last_login_at);
    }

    public function test_it_regenerates_the_session_on_sign_in(): void
    {
        $student = User::factory()->create();
        $this->startSession();
        $sessionId = session()->getId();
        $token = session()->token();
        session()->save();

        // The cookie makes the request continue this session. Without it every
        // request starts a new one, and this test would pass whatever the code did.
        $this->withCookie(config('session.cookie'), $sessionId)->attempt($student->email);

        $this->assertNotSame($sessionId, session()->getId());
        $this->assertNotSame($token, session()->token());
    }

    public function test_it_signs_out_and_regenerates_the_csrf_token(): void
    {
        $this->actingAs(User::factory()->create());
        $this->startSession();
        $token = session()->token();

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNotNull(session()->token());
        $this->assertNotSame($token, session()->token());
    }

    public function test_it_lets_an_unconfirmed_user_sign_out(): void
    {
        $this->actingAs(User::factory()->unconfirmed()->create());

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_it_redirects_a_guest_from_a_signed_in_page_to_login(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    public function test_it_redirects_the_root_by_role(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('root'))
            ->assertRedirect(route('home'));

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('root'))
            ->assertRedirect(route('admin.dashboard'));
    }

    private function attempt(string $email, string $password = 'password'): TestResponse
    {
        return $this->from(route('login'))->post(route('login'), [
            'email' => $email,
            'password' => $password,
        ]);
    }

    private function genericMessage(): string
    {
        return LoginRefused::invalidCredentials()->getMessage();
    }
}
