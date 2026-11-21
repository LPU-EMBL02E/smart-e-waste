<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

/**
 * A user changing their own details and password from /profile.
 * API_Design.md §7.3, §7.6 ("Change own details", "Change own password"), P41,
 * P46, P50.
 *
 * The session rules (other sessions end, this one stays) are checked in
 * AccountServiceTest, where the session ids are known.
 */
class OwnAccountTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->student = User::factory()->for($this->organization)->create([
            'email' => 'juan@example.com',
            'student_number' => '2023-0001',
        ]);
    }

    public function test_it_shows_both_forms_filled_with_the_users_details(): void
    {
        $this->actingAs($this->student)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee(route('profile.update'))
            ->assertSee(route('profile.password.update'))
            ->assertSee('value="juan@example.com"', false)
            ->assertSee('value="2023-0001"', false)
            ->assertSee('id="current_password"', false)
            ->assertSee('id="password_current_password"', false);
    }

    public function test_it_saves_a_students_details_without_asking_for_the_password(): void
    {
        $other = Organization::factory()->create();

        $this->updateDetails(['first_name' => 'Juana', 'student_number' => '2023-0999', 'organization_id' => $other->id])
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('status', 'Your details have been saved.');

        $student = $this->student->fresh();
        $this->assertSame('Juana', $student->first_name);
        $this->assertSame('2023-0999', $student->student_number);
        $this->assertSame($other->id, $student->organization_id);
        $this->assertTrue($student->hasVerifiedEmail());
    }

    public function test_it_asks_for_the_current_password_to_change_the_email(): void
    {
        $this->updateDetails(['email' => 'new@example.com'])
            ->assertSessionHasErrors(['current_password' => 'Enter your current password to change your email.']);

        $this->assertSame('juan@example.com', $this->student->fresh()->email);
    }

    public function test_it_refuses_a_wrong_current_password_and_changes_nothing(): void
    {
        $this->updateDetails(['email' => 'new@example.com', 'first_name' => 'Juana', 'current_password' => 'wrong-password'])
            ->assertSessionHasErrors('current_password');

        $student = $this->student->fresh();
        $this->assertSame('juan@example.com', $student->email);
        $this->assertNotSame('Juana', $student->first_name);
    }

    public function test_it_unconfirms_a_new_email_and_sends_the_link_there(): void
    {
        Notification::fake();

        $this->updateDetails(['email' => 'new@example.com', 'current_password' => 'password'])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('status', 'Your details have been saved. Confirm your new email: a link is on its way to new@example.com.');

        $student = $this->student->fresh();
        $this->assertSame('new@example.com', $student->email);
        $this->assertFalse($student->hasVerifiedEmail());
        $this->assertAuthenticatedAs($student);
        Notification::assertSentTo($student, VerifyEmail::class);

        $this->get(route('profile.show'))->assertRedirect(route('verification.notice'));
    }

    public function test_it_keeps_the_new_email_and_says_so_when_the_link_fails_to_send(): void
    {
        Notification::shouldReceive('send')->andThrow(new RuntimeException('mail is down'));

        $this->updateDetails(['email' => 'new@example.com', 'current_password' => 'password'])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('status', 'Your details have been saved, but we couldn\'t send the confirmation email. Try "Send the link again" in a moment.');

        $this->assertSame('new@example.com', $this->student->fresh()->email);
    }

    public function test_it_refuses_an_email_or_student_number_another_account_uses(): void
    {
        User::factory()->create(['email' => 'taken@example.com', 'student_number' => '2023-0002']);

        $this->updateDetails(['email' => 'taken@example.com', 'student_number' => '2023-0002', 'current_password' => 'password'])
            ->assertSessionHasErrors(['email', 'student_number']);
    }

    public function test_it_requires_a_students_student_number_and_organization(): void
    {
        $this->updateDetails(['student_number' => '', 'organization_id' => ''])
            ->assertSessionHasErrors(['student_number', 'organization_id']);
    }

    public function test_it_lets_a_student_stay_in_an_organization_that_was_switched_off(): void
    {
        $this->organization->update(['is_active' => false]);

        $this->updateDetails(['first_name' => 'Juana'])->assertSessionHasNoErrors();

        $this->assertSame('Juana', $this->student->fresh()->first_name);
    }

    public function test_it_refuses_a_move_to_an_organization_that_is_switched_off(): void
    {
        $inactive = Organization::factory()->inactive()->create();

        $this->updateDetails(['organization_id' => $inactive->id])->assertSessionHasErrors('organization_id');

        $this->assertSame($this->organization->id, $this->student->fresh()->organization_id);
    }

    public function test_it_ignores_role_and_status_in_the_form(): void
    {
        $this->updateDetails(['role' => User::ROLE_ADMIN, 'status' => User::STATUS_INACTIVE])->assertSessionHasNoErrors();

        $student = $this->student->fresh();
        $this->assertSame(User::ROLE_STUDENT, $student->role);
        $this->assertSame(User::STATUS_ACTIVE, $student->status);
    }

    public function test_it_lets_an_admin_change_their_name_but_not_add_a_student_number_or_organization(): void
    {
        $admin = User::factory()->admin()->create();
        $details = ['first_name' => 'Ada', 'last_name' => $admin->last_name, 'email' => $admin->email];

        $this->actingAs($admin)->put(route('profile.update'), $details)->assertSessionHasNoErrors();
        $this->assertSame('Ada', $admin->fresh()->first_name);

        $this->actingAs($admin)
            ->put(route('profile.update'), [...$details, 'student_number' => '2023-0003', 'organization_id' => $this->organization->id])
            ->assertSessionHasErrors(['student_number', 'organization_id']);
        $this->assertNull($admin->fresh()->student_number);
        $this->assertNull($admin->fresh()->organization_id);
    }

    public function test_it_limits_details_changes_to_six_a_minute(): void
    {
        foreach (range(1, config('ewaste.own_account_changes_per_minute')) as $attempt) {
            $this->updateDetails(['first_name' => "Juan {$attempt}"])->assertRedirect();
        }

        $this->updateDetails(['first_name' => 'One too many'])->assertTooManyRequests();
    }

    public function test_it_changes_the_password_and_keeps_the_user_signed_in(): void
    {
        $this->updatePassword(['current_password' => 'password'])
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('status', 'Your password has been changed. You have been signed out everywhere else.');

        $this->assertTrue(Hash::check('a-new-password', $this->student->fresh()->password));
        $this->assertAuthenticatedAs($this->student);
    }

    public function test_it_refuses_a_wrong_current_password_for_a_password_change(): void
    {
        $this->updatePassword(['current_password' => 'wrong-password'])
            ->assertSessionHasErrorsIn('password', 'current_password');

        $this->assertTrue(Hash::check('password', $this->student->fresh()->password));
    }

    public function test_it_requires_eight_characters_and_a_matching_confirmation(): void
    {
        $this->updatePassword(['current_password' => 'password', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrorsIn('password', 'password');
        $this->updatePassword(['current_password' => 'password', 'password_confirmation' => 'something-else'])
            ->assertSessionHasErrorsIn('password', 'password');

        $this->assertTrue(Hash::check('password', $this->student->fresh()->password));
    }

    public function test_it_shows_a_password_form_error_beside_that_form_only(): void
    {
        $this->actingAs($this->student)
            ->from(route('profile.show'))
            ->followingRedirects()
            ->put(route('profile.password.update'), ['current_password' => 'wrong-password', 'password' => 'a-new-password', 'password_confirmation' => 'a-new-password'])
            ->assertSee('<a href="#password_current_password">', false)
            ->assertSee('id="password_current_password-error"', false)
            ->assertDontSee('id="current_password-error"', false);
    }

    public function test_it_limits_password_changes_to_six_a_minute(): void
    {
        foreach (range(1, config('ewaste.own_account_changes_per_minute')) as $attempt) {
            $this->updatePassword(['current_password' => 'wrong-password'])->assertRedirect();
        }

        $this->updatePassword(['current_password' => 'password'])->assertTooManyRequests();
        $this->assertTrue(Hash::check('password', $this->student->fresh()->password));
    }

    public function test_it_keeps_guests_and_unconfirmed_users_out(): void
    {
        $this->put(route('profile.update'))->assertRedirect(route('login'));
        $this->put(route('profile.password.update'))->assertRedirect(route('login'));

        $unconfirmed = User::factory()->unconfirmed()->create();
        $this->actingAs($unconfirmed)->put(route('profile.password.update'))->assertRedirect(route('verification.notice'));
    }

    /** The student's current details, with $changes on top. */
    private function updateDetails(array $changes): TestResponse
    {
        return $this->actingAs($this->student)->put(route('profile.update'), [
            'first_name' => $this->student->first_name,
            'last_name' => $this->student->last_name,
            'email' => $this->student->email,
            'student_number' => $this->student->student_number,
            'organization_id' => $this->student->organization_id,
            ...$changes,
        ]);
    }

    private function updatePassword(array $fields): TestResponse
    {
        return $this->actingAs($this->student)->put(route('profile.password.update'), [
            'password' => 'a-new-password',
            'password_confirmation' => 'a-new-password',
            ...$fields,
        ]);
    }
}
