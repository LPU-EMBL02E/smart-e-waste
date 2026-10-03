<?php

namespace App\Modules\Devices\Support;

/**
 * Generates a bin's HMAC signing secret. API_Design.md P39.
 *
 * 32 random bytes written as 64 hex characters. The server generates it; it is
 * shown once, on the page after registration or a new-secret request, for the
 * admin to type into the bin's setup portal. Issuing a new secret stops the old
 * one at once.
 *
 * Stored with the model's `encrypted` cast, so it is encrypted at rest with
 * APP_KEY (Database_Schema.md §4).
 */
final class DeviceSecret
{
    public static function generate(): string
    {
        return bin2hex(random_bytes(config('ewaste.device.secret_bytes')));
    }
}
