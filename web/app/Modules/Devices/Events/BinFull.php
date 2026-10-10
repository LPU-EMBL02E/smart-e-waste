<?php

namespace App\Modules\Devices\Events;

use App\Modules\Devices\Models\BinTelemetry;
use App\Modules\Devices\Models\Device;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A bin just became full. API_Design.md §5.3 and P19.
 *
 * Dispatched by BinService only on the TRANSITION: when a reading is FULL and
 * the device's previous reading was not, or there was no previous reading.
 * Dispatching on every full reading would notify every admin on every telemetry
 * send for as long as the bin stayed full.
 */
class BinFull
{
    use Dispatchable;

    public function __construct(
        public readonly Device $device,
        public readonly BinTelemetry $reading,
    ) {}
}
