<?php

namespace Tests\Feature\Identity;

use App\Modules\Devices\Models\Device;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\SignsDeviceRequests;
use Tests\TestCase;

/**
 * The profile page. API_Design.md §7.3, P32, P35, P48, Database_Schema.md §3,
 * web/docs/TRACK-A-ACCOUNTS.md step A7.
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;
    use SignsDeviceRequests;

    public function test_it_shows_the_qr_token_to_its_owner(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee($student->qrCredential->token);
    }

    public function test_it_shows_no_other_users_token(): void
    {
        $student = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($student)
            ->get(route('profile.show'))
            ->assertDontSee($other->qrCredential->token);
    }

    public function test_it_sends_a_guest_to_sign_in(): void
    {
        $this->get(route('profile.show'))->assertRedirect(route('login'));
        $this->post(route('profile.qr.regenerate'))->assertRedirect(route('login'));
    }

    public function test_it_never_shows_a_token_before_the_email_is_confirmed(): void
    {
        $student = User::factory()->unconfirmed()->create();
        $token = $student->qrCredential->token;
        $this->actingAs($student);

        $this->get(route('profile.show'))->assertRedirect(route('verification.notice'));
        $this->post(route('profile.qr.regenerate'))->assertRedirect(route('verification.notice'));

        $this->assertSame($token, $student->qrCredential->fresh()->token);
    }

    public function test_it_shows_the_students_details_and_organization(): void
    {
        $organization = Organization::factory()->create([
            'name' => 'College of Engineering',
            'code' => 'COE',
            'description' => 'Engineering programs.',
        ]);
        $student = User::factory()->for($organization)->create([
            'first_name' => 'Juan',
            'last_name' => 'Cruz',
            'email' => 'juan@example.com',
            'student_number' => '2024-00123',
        ]);

        $this->actingAs($student)
            ->get(route('profile.show'))
            ->assertSee('Juan Cruz')
            ->assertSee('juan@example.com')
            ->assertSee('2024-00123')
            ->assertSee('Student')
            ->assertSee('College of Engineering')
            ->assertSee('COE')
            ->assertSee('Engineering programs.');
    }

    public function test_it_loads_what_the_page_needs_so_the_view_runs_no_query(): void
    {
        $user = User::findOrFail(User::factory()->create()->id);

        app(AccountService::class)->loadProfile($user);

        $this->assertTrue($user->relationLoaded('organization'));
        $this->assertTrue($user->relationLoaded('qrCredential'));
    }

    public function test_it_shows_a_student_their_organization_after_it_is_switched_off(): void
    {
        $organization = Organization::factory()->inactive()->create(['name' => 'Closed College']);

        $this->actingAs(User::factory()->for($organization)->create())
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Closed College');
    }

    public function test_it_shows_an_admin_has_no_organization(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('No organization (admin account)');
    }

    public function test_it_shows_dates_in_manila_time(): void
    {
        $student = User::factory()->create(['created_at' => Carbon::parse('2026-10-02 17:00:00', 'UTC')]);
        $student->qrCredential->forceFill(['updated_at' => Carbon::parse('2026-10-12 02:05:00', 'UTC')])->save();

        $this->actingAs($student)
            ->get(route('profile.show'))
            ->assertSee('03 Oct 2026')
            ->assertSee('12 Oct 2026, 10:05am');
    }

    public function test_it_regenerates_the_qr_token_and_the_old_one_stops_matching(): void
    {
        $student = User::factory()->create();
        $old = $student->qrCredential->token;

        $this->actingAs($student)
            ->post(route('profile.qr.regenerate'))
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('status', 'Your QR code has been replaced. Your old code no longer works.');

        $new = $student->qrCredential->fresh()->token;
        $this->assertNotSame($old, $new);
        $this->assertSame(config('ewaste.qr_token_length'), strlen($new));
        $this->assertDatabaseCount('user_qr_credentials', 1);

        $this->devicePost(Device::factory()->create(), '/api/v1/device/sessions', ['qr_token' => $old])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'INVALID_QR');
    }

    public function test_it_regenerates_only_the_signed_in_users_token(): void
    {
        $other = User::factory()->create();
        $token = $other->qrCredential->token;

        $this->actingAs(User::factory()->create())->post(route('profile.qr.regenerate'));

        $this->assertSame($token, $other->qrCredential->fresh()->token);
    }

    public function test_it_updates_the_last_changed_time_when_regenerating(): void
    {
        $student = User::factory()->create();
        $this->travelTo(now()->addDay()->startOfSecond());

        $this->actingAs($student)->post(route('profile.qr.regenerate'));

        $this->assertTrue($student->qrCredential->fresh()->updated_at->equalTo(now()));
    }

    public function test_it_keeps_the_last_changed_time_when_a_code_is_scanned(): void
    {
        $credential = User::factory()->create()->qrCredential;
        $changed = $credential->updated_at;
        $this->travelTo(now()->addDay()->startOfSecond());

        app(AccountService::class)->markQrCredentialUsed($credential);

        $credential->refresh();
        $this->assertTrue($credential->last_used_at->equalTo(now()));
        $this->assertTrue($credential->updated_at->equalTo($changed));
    }
}
