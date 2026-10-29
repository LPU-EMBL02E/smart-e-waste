<?php

namespace Database\Factories\Devices;

use App\Modules\Devices\Models\BinTelemetry;
use App\Modules\Devices\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Bin readings for tests. Database_Schema.md §5.
 *
 * Rows are written as given; BinService computes fill_percent and status for
 * real readings. The defaults match a 500 mm bin from DeviceFactory.
 *
 * @extends Factory<BinTelemetry>
 */
class BinTelemetryFactory extends Factory
{
    protected $model = BinTelemetry::class;

    public function definition(): array
    {
        return [
            'device_id' => Device::factory(),
            'distance_mm' => 400,
            'fill_percent' => 20,
            'status' => BinTelemetry::STATUS_OK,
            'panel_open' => false,
            'fault_code' => null,
        ];
    }

    public function full(): static
    {
        return $this->state([
            'distance_mm' => 25,
            'fill_percent' => 95,
            'status' => BinTelemetry::STATUS_FULL,
        ]);
    }
}
