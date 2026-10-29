<?php

namespace Database\Factories\Devices;

use App\Modules\Devices\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Bins for tests. Database_Schema.md §4.
 *
 * The default is an active, calibrated bin whose secret is the test secret from
 * API_Design.md §3.4, so the documented signing vectors can be sent as they are.
 *
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /** The test secret from API_Design.md §3.4. Never a real bin's secret. */
    public const SECRET = 'test-secret-do-not-use-0123456789abcdef';

    protected $model = Device::class;

    public function definition(): array
    {
        return [
            'device_code' => fake()->unique()->bothify('BIN-###'),
            'name' => fake()->words(2, true),
            'location' => null,
            'secret' => self::SECRET,
            'status' => Device::STATUS_ACTIVE,
            'empty_distance_mm' => 500,
            'fill_threshold_percent' => 90,
            'weight_offset' => 8412.5,
            'weight_scale_factor' => 21.45,
            'calibrated_at' => now(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => Device::STATUS_INACTIVE]);
    }

    /** Registered but not yet calibrated: config returns null calibration. */
    public function uncalibrated(): static
    {
        return $this->state([
            'weight_offset' => null,
            'weight_scale_factor' => null,
            'calibrated_at' => null,
        ]);
    }
}
