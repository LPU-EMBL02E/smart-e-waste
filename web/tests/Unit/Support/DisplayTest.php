<?php

namespace Tests\Unit\Support;

use App\Support\Display;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The display formats every page shares. web/docs/DESIGN.md §6;
 * AGENTS.md §2 rule 9 (stored in UTC, shown in Asia/Manila).
 */
class DisplayTest extends TestCase
{
    public function test_it_converts_times_to_the_display_zone(): void
    {
        $utc = Carbon::parse('2026-10-30 01:00:05', 'UTC');

        $this->assertSame('30 Oct 2026, 9:00am', Display::dateTime($utc));
        $this->assertSame('30 Oct 2026, 9:00:05am', Display::dateTimeWithSeconds($utc));
        $this->assertSame('30 Oct 2026', Display::date($utc));
    }

    public function test_it_crosses_midnight_when_converting_a_date(): void
    {
        $this->assertSame('31 Oct 2026', Display::date(Carbon::parse('2026-10-30 16:30:00', 'UTC')));
    }

    public function test_it_shows_the_given_text_for_a_missing_time(): void
    {
        $this->assertSame('—', Display::dateTime(null));
        $this->assertSame('Never', Display::dateTime(null, 'Never'));
    }

    public function test_it_formats_grams_without_losing_precision(): void
    {
        $this->assertSame('184.6 g', Display::grams('184.60'));
        $this->assertSame('184.65 g', Display::grams('184.65'));
        $this->assertSame('1,200.0 g', Display::grams(1200));
    }

    public function test_it_shows_no_value_rather_than_zero_for_missing_grams(): void
    {
        $this->assertSame('—', Display::grams(null));
    }

    public function test_it_uses_a_true_minus_on_negative_points(): void
    {
        $this->assertSame('−80 pts', Display::points(-80));
        $this->assertSame('−1,200', Display::wholeNumber(-1200));
    }

    public function test_it_trims_a_whole_percentage(): void
    {
        $this->assertSame('82%', Display::percent('82.00'));
        $this->assertSame('82.5%', Display::percent('82.50'));
    }

    public function test_it_writes_documented_states_in_sentence_case(): void
    {
        $this->assertSame('Completed', Display::statusLabel('COMPLETED'));
        $this->assertSame('OK', Display::statusLabel('OK'));
        $this->assertSame('ok', Display::statusTone('COMPLETED'));
        $this->assertSame('warn', Display::statusTone('FULL'));
        $this->assertSame('danger', Display::statusTone('VOIDED'));
    }

    public function test_it_writes_a_fault_code_in_words(): void
    {
        $this->assertSame('Camera unavailable', Display::faultLabel('CAMERA_UNAVAILABLE'));
    }
}
