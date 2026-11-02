<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * How values read on a page, in one place. web/docs/DESIGN.md §§4, 7.
 *
 * Only formatting: it never computes points or decides a status. The views use
 * these through the Blade directives in AppServiceProvider, and the dashboard's
 * script repeats statusLabel(), faultLabel() and dateTime() for the rows it
 * redraws.
 */
final class Display
{
    private const string EMPTY = '—';

    /** Every timestamp is stored in UTC and shown in the display time zone. */
    public static function dateTime(?CarbonInterface $time, string $empty = self::EMPTY): string
    {
        return self::inDisplayZone($time, 'd M Y, g:ia', $empty);
    }

    public static function dateTimeWithSeconds(?CarbonInterface $time, string $empty = self::EMPTY): string
    {
        return self::inDisplayZone($time, 'd M Y, g:i:sa', $empty);
    }

    public static function date(?CarbonInterface $time, string $empty = self::EMPTY): string
    {
        return self::inDisplayZone($time, 'd M Y', $empty);
    }

    /** "184.6 g", "1,240.0 g", "184.65 g": no stored digit is ever lost. */
    public static function grams(string|int|float|null $grams): string
    {
        if ($grams === null) {
            return self::EMPTY;
        }

        $formatted = number_format((float) $grams, 2);

        return (str_ends_with($formatted, '0') ? substr($formatted, 0, -1) : $formatted).' g';
    }

    public static function kilograms(string|int|float $grams): string
    {
        return number_format((float) $grams / 1000, 2).' kg';
    }

    /** "240 pts", and "−12 pts" for a balance below zero after a void. */
    public static function points(string|int|null $points): string
    {
        if ($points === null) {
            return self::EMPTY;
        }

        return self::wholeNumber($points).' pts';
    }

    /** "1,284", with a true minus sign. */
    public static function wholeNumber(string|int $number): string
    {
        return str_replace('-', '−', number_format((int) $number));
    }

    /** "17.5%", "90%": the percentage the server worked out, without padding. */
    public static function percent(string|int|float $percent): string
    {
        return rtrim(rtrim(number_format((float) $percent, 2), '0'), '.').'%';
    }

    /** A stored state as a readable label: COMPLETED reads "Completed". */
    public static function statusLabel(string $status): string
    {
        return $status === 'OK' ? $status : Str::ucfirst(Str::lower($status));
    }

    /** The colour that supports a documented state. design-system.html #status. */
    public static function statusTone(string $status): ?string
    {
        return match (Str::upper($status)) {
            'COMPLETED', 'FULFILLED', 'OK', 'ACTIVE' => 'ok',
            'PENDING', 'FULL' => 'warn',
            'VOIDED', 'CANCELLED' => 'danger',
            default => null,
        };
    }

    /** ACTUATOR_JAM reads "Actuator jam". */
    public static function faultLabel(string $faultCode): string
    {
        return Str::ucfirst(Str::lower(str_replace('_', ' ', $faultCode)));
    }

    private static function inDisplayZone(?CarbonInterface $time, string $format, string $empty): string
    {
        return $time?->timezone(config('ewaste.display_timezone'))->format($format) ?? $empty;
    }
}
