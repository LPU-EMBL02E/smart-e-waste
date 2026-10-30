<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Devices\Events\BinFull;
use App\Modules\Notifications\Support\AdminNotifier;
use App\Modules\Notifications\Support\NotificationType;

/**
 * BinFull -> ONE notification row per admin user.
 * API_Design.md §7.7 and Database_Schema.md §12, type BIN_FULL.
 *
 * Students are not notified: a full bin is an operational problem, and the bin
 * itself shows its bin-full screen to anyone standing at it.
 *
 * QUEUE_CONNECTION is `sync`, so this runs inside the telemetry request. A
 * failure is reported and swallowed: the reading is already stored and the bin
 * must still get its reply.
 */
class SendBinFullNotifications
{
    public function __construct(private AdminNotifier $admins) {}

    public function handle(BinFull $event): void
    {
        rescue(fn () => $this->admins->notify(
            NotificationType::BinFull,
            'Bin full',
            sprintf(
                '%s is %s%% full and is refusing deposits until it is emptied.',
                $event->device->name,
                (float) $event->reading->fill_percent,
            ),
        ));
    }
}
