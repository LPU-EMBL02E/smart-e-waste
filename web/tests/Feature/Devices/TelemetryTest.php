<?php

namespace Tests\Feature\Devices;

use App\Modules\Devices\Models\BinTelemetry;
use App\Modules\Devices\Models\Device;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Support\NotificationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsDeviceRequests;
use Tests\TestCase;

/**
 * POST /api/v1/device/telemetry. API_Design.md §5.3, P19, P42, P43;
 * Database_Schema.md §5 and §12.
 *
 * The factory bin is 500 mm deep with a 90 % threshold.
 */
class TelemetryTest extends TestCase
{
    use RefreshDatabase;
    use SignsDeviceRequests;

    private const PATH = '/api/v1/device/telemetry';

    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->device = Device::factory()->create();
    }

    public function test_it_computes_fill_percent_and_clamps_it(): void
    {
        $this->sendReading(412.5)->assertCreated()->assertExactJson(['fill_percent' => 17.5, 'status' => 'OK']);
        $this->sendReading(650)->assertCreated()->assertJsonPath('fill_percent', 0);
        $this->sendReading(0)->assertCreated()->assertJsonPath('fill_percent', 100);
    }

    public function test_it_marks_a_reading_full_at_the_threshold(): void
    {
        $this->sendReading(50.1)->assertJsonPath('fill_percent', 89.98)->assertJsonPath('status', 'OK');
        $this->sendReading(50)->assertJsonPath('fill_percent', 90)->assertJsonPath('status', 'FULL');
    }

    public function test_it_stores_panel_open_and_the_fault_code(): void
    {
        $this->sendReading(400, panelOpen: true, fault: 'ACTUATOR_JAM')->assertCreated();

        $this->assertDatabaseHas('bin_telemetry', [
            'device_id' => $this->device->id,
            'distance_mm' => 400,
            'fill_percent' => 20,
            'status' => BinTelemetry::STATUS_OK,
            'panel_open' => true,
            'fault_code' => 'ACTUATOR_JAM',
        ]);
    }

    public function test_it_rejects_an_unknown_fault_code(): void
    {
        $this->sendReading(400, fault: 'ON_FIRE')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertDatabaseCount('bin_telemetry', 0);
    }

    public function test_it_notifies_admins_once_when_a_bin_turns_full(): void
    {
        $admins = User::factory()->admin()->count(2)->create();
        User::factory()->create();

        $this->sendReading(400);
        $this->sendReading(30);
        $this->sendReading(20);

        $this->assertSame(2, Notification::where('type', NotificationType::BinFull)->count());
        $this->assertEqualsCanonicalizing(
            $admins->modelKeys(),
            Notification::pluck('user_id')->all(),
        );
    }

    public function test_it_notifies_admins_once_when_a_new_fault_appears(): void
    {
        User::factory()->admin()->create();

        $this->sendReading(400, fault: 'CAMERA_UNAVAILABLE');
        $this->sendReading(400, fault: 'CAMERA_UNAVAILABLE');
        $this->assertSame(1, Notification::where('type', NotificationType::BinFault)->count());

        $this->sendReading(400, fault: 'ACTUATOR_JAM');
        $this->assertSame(2, Notification::where('type', NotificationType::BinFault)->count());
    }

    public function test_it_does_not_notify_for_an_open_panel(): void
    {
        User::factory()->admin()->create();

        $this->sendReading(400, panelOpen: true)->assertCreated();

        $this->assertDatabaseCount('notifications', 0);
    }

    private function sendReading(float $distanceMm, bool $panelOpen = false, ?string $fault = null)
    {
        return $this->devicePost($this->device, self::PATH, [
            'distance_mm' => $distanceMm,
            'panel_open' => $panelOpen,
            'fault_code' => $fault,
        ]);
    }
}
