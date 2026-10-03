<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Devices\Events\BinFull;

/**
 * BinFull -> ONE notification row per admin user.
 * API_Design.md §7.7 and Database_Schema.md §12, type BIN_FULL.
 *
 * Students are not notified: a full bin is an operational problem, and the bin
 * itself shows its bin-full screen to anyone standing at it.
 *
 * TODO: method body.
 */
class SendBinFullNotifications
{
    public function handle(BinFull $event): void
    {
        throw new \LogicException('Not implemented.');
    }
}
