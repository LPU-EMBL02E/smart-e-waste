<?php

namespace Tests\Feature\Reporting;

use App\Modules\Deposits\Models\DepositSession;
use App\Modules\Deposits\Models\Transaction;
use App\Modules\Devices\Models\BinTelemetry;
use App\Modules\Devices\Models\Device;
use App\Modules\Devices\Support\BinFaultCode;
use App\Modules\Identity\Models\User;
use App\Modules\Rewards\Models\Redemption;
use Database\Factories\Deposits\TransactionFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The admin dashboard and its live summary. API_Design.md §7.4, §7.5, P34;
 * System_Plan.md §6.3.
 *
 * The clock is fixed at 09:00 in Manila, 01:00 UTC, so local midnight is
 * 16:00 UTC the day before.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private Device $bin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ewaste.display_timezone' => 'Asia/Manila']);
        $this->travelTo(Carbon::parse('2026-10-30 01:00:00', 'UTC'));

        $this->bin = Device::factory()->create(['name' => 'Bin 1', 'last_seen_at' => now()->subSeconds(18)]);
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_it_returns_the_summary_fields_exactly(): void
    {
        BinTelemetry::factory()->for($this->bin)->create([
            'fill_percent' => 17.5,
            'panel_open' => true,
            'fault_code' => BinFaultCode::cases()[0],
        ]);
        $this->transactionAtBin()->create(['points_awarded' => 18]);
        $this->transactionAtBin()->create(['points_awarded' => 20]);
        Redemption::factory()->create();
        Redemption::factory()->fulfilled()->create();
        DepositSession::factory()->accepted()->for($this->bin)->create(['created_at' => now()->subMinutes(30)]);

        $this->getJson(route('admin.dashboard.summary'))
            ->assertOk()
            ->assertExactJson([
                'generated_at' => '2026-10-30T01:00:00Z',
                'bins' => [[
                    'id' => $this->bin->id,
                    'name' => 'Bin 1',
                    'fill_percent' => 17.5,
                    'status' => BinTelemetry::STATUS_OK,
                    'panel_open' => true,
                    'fault_code' => BinFaultCode::cases()[0]->value,
                    'last_seen_at' => '2026-10-30T00:59:42Z',
                ]],
                'today' => ['deposits' => 2, 'weight_g' => 369.2, 'points_awarded' => 38],
                'pending_redemptions' => 1,
                'sessions_awaiting_review' => 1,
            ]);
    }

    public function test_it_leaves_out_retired_bins(): void
    {
        $inactive = Device::factory()->inactive()->create();
        Device::factory()->create(['status' => Device::STATUS_RETIRED]);

        $this->assertSame(
            [$this->bin->id, $inactive->id],
            $this->summary()->json('bins.*.id'),
        );
    }

    public function test_it_returns_nulls_for_a_bin_with_no_reading(): void
    {
        $this->summary()->assertJsonPath('bins.0', [
            'id' => $this->bin->id,
            'name' => 'Bin 1',
            'fill_percent' => null,
            'status' => null,
            'panel_open' => null,
            'fault_code' => null,
            'last_seen_at' => '2026-10-30T00:59:42Z',
        ]);
    }

    public function test_it_counts_today_from_manila_midnight(): void
    {
        $this->transactionAtBin()->create(['created_at' => '2026-10-29 16:00:00', 'points_awarded' => 7]);
        $this->transactionAtBin()->create(['created_at' => '2026-10-29 15:59:59', 'points_awarded' => 100]);

        $this->summary()->assertJsonPath('today', ['deposits' => 1, 'weight_g' => 184.6, 'points_awarded' => 7]);
    }

    public function test_it_leaves_voided_transactions_out_of_today(): void
    {
        $this->transactionAtBin()->create(['points_awarded' => 7]);
        $this->transactionAtBin()->voided()->create(['points_awarded' => 100]);

        $this->summary()->assertJsonPath('today', ['deposits' => 1, 'weight_g' => 184.6, 'points_awarded' => 7]);
    }

    public function test_it_returns_utc_times(): void
    {
        $this->summary()
            ->assertJsonPath('generated_at', '2026-10-30T01:00:00Z')
            ->assertJsonPath('bins.0.last_seen_at', '2026-10-30T00:59:42Z');
    }

    public function test_it_renders_the_summary_on_the_page_in_manila_time(): void
    {
        $this->transactionAtBin()->create(['points_awarded' => 18]);

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('30 Oct 2026, 9:00am')
            ->assertSee('30 Oct 2026, 8:59am')
            ->assertSeeInOrder(['Deposits', '1', 'Weight collected', '184.6', 'Points awarded', '18'])
            ->assertSee(json_encode(route('admin.dashboard.summary')), false);
    }

    public function test_it_keeps_students_out_of_the_summary(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson(route('admin.dashboard.summary'))->assertForbidden();
        $this->get(route('admin.dashboard'))->assertForbidden();
    }

    // ---------------------------------------------------------------- helpers

    private function summary(): TestResponse
    {
        return $this->getJson(route('admin.dashboard.summary'))->assertOk();
    }

    /** A completed deposit of 184.6 g at $this->bin, one session per transaction. */
    private function transactionAtBin(): TransactionFactory
    {
        $bin = $this->bin;

        return Transaction::factory()->state(fn () => [
            'session_id' => DepositSession::factory()->completed()->for($bin),
        ]);
    }
}
