<?php

namespace Tests\Feature\Deposits;

use App\Modules\Deposits\Models\DepositSession;
use App\Modules\Deposits\Models\Transaction;
use App\Modules\Deposits\Services\DepositService;
use App\Modules\Deposits\Support\SessionStatus;
use App\Modules\Devices\Models\Device;
use App\Modules\Rewards\Models\RewardRule;
use App\Support\Device\DeviceErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\SignsDeviceRequests;
use Tests\TestCase;

/**
 * What deposit, complete and cancel answer in each session state, and the
 * timeout sweep. API_Design.md §4.1, §4.2, P8, P10, P12.
 */
class SessionStateTest extends TestCase
{
    use RefreshDatabase;
    use SignsDeviceRequests;

    private const BODIES = [
        'deposit' => [
            'weight_g' => 184.6,
            'weight_stable' => true,
            'samples' => 20,
            'verification' => ['method' => 'cam_diff', 'passed' => true, 'score' => 0.87],
        ],
        'complete' => ['actuator_ok' => true, 'cycle_ms' => 3200],
        'cancel' => [],
    ];

    private RewardRule $rule;

    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule = RewardRule::factory()->create();
        $this->device = Device::factory()->create();
    }

    /**
     * One row of the API_Design.md §4.2 table per case. The REJECTED session
     * recorded ZERO_WEIGHT, so its 422 proves the recorded code is replayed.
     */
    #[DataProvider('stateTable')]
    public function test_it_answers_every_state_as_the_state_table(string $state, string $call, int $status, ?string $code): void
    {
        $session = $this->sessionIn($state);

        $response = $this->devicePost($this->device, "/api/v1/device/sessions/{$session->id}/{$call}", self::BODIES[$call])
            ->assertStatus($status);

        $code === null
            ? $response->assertJsonMissingPath('error')
            : $response->assertJsonPath('error.code', $code);
    }

    public static function stateTable(): array
    {
        $table = [
            //               deposit                             complete                           cancel
            'open' => [[200, null], [409, 'INVALID_SESSION_STATE'], [200, null]],
            'stale' => [[410, 'SESSION_EXPIRED'], [410, 'SESSION_EXPIRED'], [410, 'SESSION_EXPIRED']],
            'accepted' => [[200, null], [200, null], [409, 'INVALID_SESSION_STATE']],
            'completed' => [[409, 'INVALID_SESSION_STATE'], [200, null], [409, 'INVALID_SESSION_STATE']],
            'failed' => [[409, 'INVALID_SESSION_STATE'], [200, null], [409, 'INVALID_SESSION_STATE']],
            'rejected' => [[422, 'ZERO_WEIGHT'], [409, 'INVALID_SESSION_STATE'], [409, 'INVALID_SESSION_STATE']],
            'expired' => [[410, 'SESSION_EXPIRED'], [410, 'SESSION_EXPIRED'], [410, 'SESSION_EXPIRED']],
            'cancelled' => [[409, 'INVALID_SESSION_STATE'], [409, 'INVALID_SESSION_STATE'], [200, null]],
        ];

        $cases = [];
        foreach ($table as $state => $answers) {
            foreach (['deposit', 'complete', 'cancel'] as $i => $call) {
                $cases["{$state} {$call}"] = [$state, $call, ...$answers[$i]];
            }
        }

        return $cases;
    }

    public function test_it_hides_another_devices_session(): void
    {
        $session = DepositSession::factory()->accepted()->create();

        foreach (self::BODIES as $call => $body) {
            $this->devicePost($this->device, "/api/v1/device/sessions/{$session->id}/{$call}", $body)
                ->assertNotFound()
                ->assertJsonPath('error.code', 'SESSION_NOT_FOUND');
        }

        $this->assertSame(SessionStatus::Accepted, $session->fresh()->status);
    }

    public function test_it_expires_only_open_sessions_in_the_sweep(): void
    {
        $stale = DepositSession::factory()->stale()->create();
        $fresh = DepositSession::factory()->create();
        $waiting = DepositSession::factory()->accepted()->create(['expires_at' => now()->subHour()]);

        $this->assertSame(1, app(DepositService::class)->expireOpenSessions());

        $this->assertSame(SessionStatus::Expired, $stale->fresh()->status);
        $this->assertNotNull($stale->fresh()->closed_at);
        $this->assertSame(SessionStatus::Open, $fresh->fresh()->status);
        $this->assertSame(SessionStatus::Accepted, $waiting->fresh()->status);
    }

    private function sessionIn(string $state): DepositSession
    {
        $factory = DepositSession::factory()->for($this->device);

        $session = match ($state) {
            'open' => $factory->create(),
            'rejected' => $factory->rejected(DeviceErrorCode::ZeroWeight)->create(),
            default => $factory->{$state}()->create(),
        };

        if ($state === 'completed') {
            Transaction::factory()->for($session, 'session')->create(['reward_rule_id' => $this->rule->id]);
        }

        return $session;
    }
}
