<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

/**
 * Self-registration and email confirmation. API_Design.md §7.2, §7.6, P41, P46,
 * P48, System_Plan.md §4, web/docs/TRACK-A-ACCOUNTS.md step A4.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'juan@example.com';

    public function test_it_shows_only_active_organizations_on_the_form(): void
    {
        Organization::factory()->create(['name' => 'College of Engineering']);
        Organization::factory()->inactive()->create(['name' => 'Closed College']);

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('College of Engineering')
            ->assertDontSee('Closed College');
    }

    public function test_it_registers_a_student_and_signs_them_in(): void
    {
        Notification::fake();

        $this->register()
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHasNoErrors();

        $user = User::where('email', self::EMAIL)->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->isStudent());
        $this->assertTrue($user->isActive());
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertTrue(Hash::check('correct-horse', $user->password));
        $this->assertNotNull($user->qrCredential);
    }

    public function test_it_sends_the_confirmation_email(): void
    {
        Notification::fake();

        $this->register()->assertSessionHas('status', fn (string $status): bool => str_contains($status, self::EMAIL));

        Notification::assertSentToTimes(User::where('email', self::EMAIL)->firstOrFail(), VerifyEmail::class, 1);
    }

    public function test_it_keeps_the_account_when_the_email_fails(): void
    {
        Exceptions::fake();
        Notification::shouldReceive('send')->andThrow(new RuntimeException('mail is down'));

        $this->register()
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'Send the link again'));

        $this->assertAuthenticatedAs(User::where('email', self::EMAIL)->firstOrFail());
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_it_requires_a_unique_student_number_and_email(): void
    {
        User::factory()->create(['email' => self::EMAIL, 'student_number' => '2024-00123']);

        $this->register(['student_number' => '2024-00123'])->assertSessionHasErrors(['email', 'student_number']);

        $this->assertGuest();
    }

    public function test_it_requires_a_student_number(): void
    {
        $this->register(['student_number' => ''])->assertSessionHasErrors('student_number');
    }

    public function test_it_refuses_a_new_student_in_an_inactive_organization(): void
    {
        $organization = Organization::factory()->inactive()->create();

        $this->register(['organization_id' => $organization->id])->assertSessionHasErrors('organization_id');
    }

    public function test_it_requires_a_confirmed_password_of_at_least_eight_characters(): void
    {
        $this->register(['password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors('password');

        $this->register(['password_confirmation' => 'different'])->assertSessionHasErrors('password');
    }

    public function test_it_never_creates_an_admin_by_registration(): void
    {
        Notification::fake();

        $this->register(['role' => User::ROLE_ADMIN, 'status' => User::STATUS_INACTIVE]);

        $user = User::where('email', self::EMAIL)->firstOrFail();
        $this->assertTrue($user->isStudent());
        $this->assertTrue($user->isActive());
    }

    public function test_it_keeps_old_input_except_the_passwords_after_a_failure(): void
    {
        $this->register(['first_name' => '']);

        $this->assertSame(self::EMAIL, session()->getOldInput('email'));
        $this->assertNull(session()->getOldInput('password'));
        $this->assertNull(session()->getOldInput('password_confirmation'));
    }

    public function test_it_limits_registration_to_five_attempts_a_minute(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->register(['first_name' => ''])->assertSessionHasErrors('first_name');
        }

        $this->register(['first_name' => ''])->assertTooManyRequests();
    }

    public function test_it_limits_registration_by_ip_address(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->register(['first_name' => '']);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->register(['first_name' => ''])
            ->assertSessionHasErrors('first_name');
    }

    public function test_it_does_not_count_opening_the_form_towards_the_limit(): void
    {
        foreach (range(1, 6) as $attempt) {
            $this->get(route('register'))->assertOk();
        }
    }

    public function test_it_shows_an_unconfirmed_user_the_confirmation_page(): void
    {
        $user = User::factory()->unconfirmed()->create();

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertSee($user->email)
            ->assertSee('Send the link again')
            ->assertSee('Sign out');
    }

    public function test_it_sends_a_confirmed_user_away_from_the_confirmation_page(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('verification.notice'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_it_hides_the_qr_page_from_an_unconfirmed_user(): void
    {
        $this->actingAs(User::factory()->unconfirmed()->create())
            ->get(route('profile.show'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_it_confirms_the_email_from_the_link_in_the_email(): void
    {
        Notification::fake();
        $this->register();
        $user = User::where('email', self::EMAIL)->firstOrFail();

        $url = null;
        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $mail) use ($user, &$url): bool {
            $url = $mail->toMail($user)->actionUrl;

            return true;
        });

        $this->get($url)->assertRedirect(route('home'))->assertSessionHas('status');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->get(route('home'))->assertOk();
    }

    public function test_it_accepts_a_confirmation_link_used_twice(): void
    {
        $user = User::factory()->unconfirmed()->create();
        $url = $this->confirmationUrl($user);

        $this->actingAs($user)->get($url)->assertRedirect(route('home'));
        $confirmedAt = $user->fresh()->email_verified_at;

        $this->travel(1)->minutes();

        $this->get($url)->assertRedirect(route('home'));
        $this->assertEquals($confirmedAt, $user->fresh()->email_verified_at);
    }

    public function test_it_rejects_a_tampered_confirmation_link(): void
    {
        $user = User::factory()->unconfirmed()->create();

        $this->actingAs($user)
            ->get($this->confirmationUrl($user).'x')
            ->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_it_rejects_a_signed_link_with_the_wrong_hash(): void
    {
        $user = User::factory()->unconfirmed()->create();

        $this->actingAs($user)
            ->get($this->confirmationUrl($user, hash: sha1('other@example.com')))
            ->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_it_rejects_someone_elses_confirmation_link(): void
    {
        $owner = User::factory()->unconfirmed()->create();
        $other = User::factory()->unconfirmed()->create();

        $this->actingAs($other)
            ->get($this->confirmationUrl($owner))
            ->assertForbidden();

        $this->assertFalse($owner->fresh()->hasVerifiedEmail());
        $this->assertFalse($other->fresh()->hasVerifiedEmail());
    }

    public function test_it_rejects_an_expired_confirmation_link(): void
    {
        $user = User::factory()->unconfirmed()->create();
        $url = $this->confirmationUrl($user);

        $this->travel(61)->minutes();

        $this->actingAs($user)->get($url)->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_it_sends_a_guest_with_a_confirmation_link_to_login(): void
    {
        $user = User::factory()->unconfirmed()->create();

        $this->get($this->confirmationUrl($user))->assertRedirect(route('login'));

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_it_resends_the_confirmation_link(): void
    {
        Notification::fake();
        $user = User::factory()->unconfirmed()->create();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('status', fn (string $status): bool => str_contains($status, $user->email));

        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
    }

    public function test_it_says_so_when_a_resend_fails(): void
    {
        Exceptions::fake();
        Notification::shouldReceive('send')->andThrow(new RuntimeException('mail is down'));

        $this->actingAs(User::factory()->unconfirmed()->create())
            ->post(route('verification.send'))
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'couldn\'t send'));

        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_it_sends_nothing_to_a_confirmed_user(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('verification.send'))->assertRedirect(route('home'));

        Notification::assertNothingSent();
    }

    public function test_it_limits_resends_to_six_a_minute(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->unconfirmed()->create());

        foreach (range(1, 6) as $attempt) {
            $this->post(route('verification.send'))->assertRedirect(route('verification.notice'));
        }

        $this->post(route('verification.send'))->assertTooManyRequests();
    }

    public function test_it_limits_resends_per_user(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->unconfirmed()->create());

        foreach (range(1, 6) as $attempt) {
            $this->post(route('verification.send'));
        }

        $this->actingAs(User::factory()->unconfirmed()->create())
            ->post(route('verification.send'))
            ->assertRedirect(route('verification.notice'));
    }

    private function register(array $overrides = []): TestResponse
    {
        return $this->from(route('register'))->post(route('register'), [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => self::EMAIL,
            'student_number' => '2024-00123',
            'organization_id' => Organization::factory()->create()->id,
            'password' => 'correct-horse',
            'password_confirmation' => 'correct-horse',
            ...$overrides,
        ]);
    }

    private function confirmationUrl(User $user, ?string $hash = null): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => $hash ?? sha1($user->email),
        ]);
    }
}
