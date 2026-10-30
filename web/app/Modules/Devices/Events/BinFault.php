<?php

namespace App\Modules\Devices\Events;

use App\Modules\Devices\Models\BinTelemetry;
use App\Modules\Devices\Models\Device;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A bin reported a new fault. API_Design.md §5.3 and P43.
 *
 * Dispatched by BinService when a reading carries a fault that differs from the
 * device's previous reading, or there was no previous reading. A bin that keeps
 * reporting the same fault does not alert again, and an open service panel never
 * alerts: it is opened on purpose.
 */
class BinFault
{
    use Dispatchable;

    public function __construct(
        public readonly Device $device,
        public readonly BinTelemetry $reading,
    ) {}
}
