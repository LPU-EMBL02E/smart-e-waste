<?php

namespace Tests\Feature\Devices;

use App\Modules\Devices\Models\Device;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsDeviceRequests;
use Tests\TestCase;

/**
 * The bin admin pages. API_Design.md §7.4, §7.6 ("Register or edit a bin"),
 * P39.
 */
class AdminBinTest extends TestCase
{
    use RefreshDatabase;
    use SignsDeviceRequests;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_it_registers_a_bin_and_shows_the_secret_once(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.bins.store'), [
            'device_code' => 'BIN-01',
            'name' => 'Library bin',
            'empty_distance_mm' => 500,
            'fill_threshold_percent' => 90,
            'status' => Device::STATUS_ACTIVE,
        ]);

        $device = Device::where('device_code', 'BIN-01')->sole();
        $response->assertRedirect(route('admin.bins.show', $device));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $device->secret);

        $this->get(route('admin.bins.show', $device))->assertOk()->assertSee($device->secret);
        $this->get(route('admin.bins.show', $device))->assertOk()->assertDontSee($device->secret);
    }

    public function test_it_rotates_the_secret_and_the_old_one_stops_working(): void
    {
        $device = Device::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.bins.secret', $device))
            ->assertRedirect(route('admin.bins.show', $device));

        $this->deviceGet($device, '/api/v1/device/config')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'INVALID_SIGNATURE');

        $this->deviceGet($device->fresh(), '/api/v1/device/config')->assertOk();
    }

    public function test_it_sets_calibrated_at_when_calibration_changes(): void
    {
        $device = Device::factory()->uncalibrated()->create();
        $form = [
            'device_code' => $device->device_code,
            'name' => $device->name,
            'empty_distance_mm' => 500,
            'fill_threshold_percent' => 90,
            'weight_offset' => 8412.5,
            'weight_scale_factor' => 21.45,
            'status' => Device::STATUS_ACTIVE,
        ];

        $this->actingAs($this->admin)->put(route('admin.bins.update', $device), $form)->assertRedirect();
        $calibratedAt = $device->fresh()->calibrated_at;
        $this->assertNotNull($calibratedAt);

        // Saving the same values again leaves the time alone.
        $this->travel(1)->hours();
        $this->put(route('admin.bins.update', $device), $form);
        $this->assertTrue($device->fresh()->calibrated_at->equalTo($calibratedAt));

        $this->put(route('admin.bins.update', $device), ['weight_offset' => 8000] + $form);
        $this->assertTrue($device->fresh()->calibrated_at->isAfter($calibratedAt));
    }

    public function test_it_keeps_students_out_of_the_bin_pages(): void
    {
        $device = Device::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.bins.index'))->assertForbidden();
        $this->get(route('admin.bins.show', $device))->assertForbidden();
        $this->post(route('admin.bins.secret', $device))->assertForbidden();
    }
}
