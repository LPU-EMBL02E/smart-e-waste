<?php

namespace App\Modules\Identity\Support;

/**
 * The colour theme a visitor chose for this browser. API_Design.md §7.6
 * ("Choose a theme"), P52; web/docs/DESIGN.md §3, "Dark theme".
 *
 * System follows the device's light or dark setting, and is what a browser
 * with no cookie gets.
 */
enum Theme: string
{
    case System = 'system';
    case Light = 'light';
    case Dark = 'dark';

    public const COOKIE = 'theme';

    /** An unknown or missing cookie value counts as following the device. */
    public static function fromCookie(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::System;
    }

    public function label(): string
    {
        return match ($this) {
            self::System => 'Device',
            self::Light => 'Light',
            self::Dark => 'Dark',
        };
    }
}
