<?php

namespace Tests\Feature\Devices;

use App\Modules\Devices\Models\Device;
use App\Modules\Rewards\Models\RewardRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsDeviceRequests;
use Tests\TestCase;

/**
 * GET /api/v1/device/config. API_Design.md §5.2, P15.
 */
class ConfigTest extends TestCase
{
    use RefreshDatabase;
    use SignsDeviceRequests;

    private const PATH = '/api/v1/device/config';

    public function test_it_returns_config_with_nulls_when_no_rule_is_in_effect(): void
    {
        RewardRule::factory()->between(now()->subDays(2), now()->subDay())->create();
        $device = Device::factory()->uncalibrated()->create();

        $this->deviceGet($device, self::PATH)
            ->assertOk()
            ->assertExactJson([
                'session_timeout_s' => config('ewaste.session_timeout_seconds'),
                'minimum_weight_g' => null,
                'maximum_weight_g' => null,
                'fill_threshold_percent' => 90,
                'calibration' => [
                    'weight_offset' => null,
                    'weight_scale_factor' => null,
                    'calibrated_at' => null,
                ],
            ]);
    }

    public function test_it_returns_the_calibration_values(): void
    {
        RewardRule::factory()->create(['minimum_weight_g' => 50, 'maximum_weight_g' => null]);
        $device = Device::factory()->create(['calibrated_at' => now()->subHour()]);

        $this->deviceGet($device, self::PATH)
            ->assertOk()
            ->assertJsonPath('minimum_weight_g', 50)
            ->assertJsonPath('maximum_weight_g', null)
            ->assertJsonPath('calibration', [
                'weight_offset' => 8412.5,
                'weight_scale_factor' => 21.45,
                'calibrated_at' => $device->calibrated_at->getTimestamp(),
            ]);
    }
}
