<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Devices\Events\BinFault;
use App\Modules\Notifications\Support\AdminNotifier;
use App\Modules\Notifications\Support\NotificationType;

/**
 * BinFault -> ONE notification row per admin user.
 * API_Design.md §7.7 and P43, Database_Schema.md §12, type BIN_FAULT.
 *
 * QUEUE_CONNECTION is `sync`, so this runs inside the telemetry request. A
 * failure is reported and swallowed: the reading is already stored and the bin
 * must still get its reply.
 */
class SendBinFaultNotifications
{
    public function __construct(private AdminNotifier $admins) {}

    public function handle(BinFault $event): void
    {
        rescue(fn () => $this->admins->notify(
            NotificationType::BinFault,
            'Bin fault',
            sprintf(
                '%s reported the fault %s. It takes no deposits while the fault lasts.',
                $event->device->name,
                $event->reading->fault_code->value,
            ),
        ));
    }
}
