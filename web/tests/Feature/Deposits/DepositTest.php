<?php

namespace Tests\Feature\Deposits;

use App\Modules\Deposits\Models\DepositSession;
use App\Modules\Deposits\Support\SessionStatus;
use App\Modules\Devices\Models\Device;
use App\Modules\Rewards\Models\RewardRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\SignsDeviceRequests;
use Tests\TestCase;

/**
 * POST /api/v1/device/sessions/{id}/deposit. API_Design.md §5.5, P12, P22, P23.
 *
 * The factory rule: 0.1 points per gram, 10 g to 2000 g.
 */
class DepositTest extends TestCase
{
    use RefreshDatabase;
    use SignsDeviceRequests;

    private const VALID = [
        'weight_g' => 184.6,
        'weight_stable' => true,
        'samples' => 20,
        'verification' => ['method' => 'cam_diff', 'passed' => true, 'score' => 0.87],
    ];

    private Device $device;

    private DepositSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        RewardRule::factory()->create();
        $this->device = Device::factory()->create();
        $this->session = DepositSession::factory()->for($this->device)->create();
    }

    public function test_it_accepts_a_valid_deposit_with_a_points_preview(): void
    {
        $this->deposit(self::VALID)
            ->assertOk()
            ->assertExactJson(['accepted' => true, 'points_preview' => 18]);

        $this->assertSame(SessionStatus::Accepted, $this->session->fresh()->status);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('point_ledger', 0);
    }

    #[DataProvider('depositChecks')]
    public function test_it_rejects_each_deposit_check_and_keeps_the_evidence(array $change, string $code): void
    {
        $payload = array_replace_recursive(self::VALID, $change);

        $this->deposit($payload)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', $code);

        $session = $this->session->fresh();
        $this->assertSame(SessionStatus::Rejected, $session->status);
        $this->assertSame($code, $session->failure_code);
        $this->assertNotNull($session->closed_at);
        $this->assertEquals($payload['weight_g'], (float) $session->net_weight_g);
        $this->assertSame($payload['weight_stable'], $session->is_weight_stable);
        $this->assertSame(20, $session->sample_count);
        $this->assertSame('cam_diff', $session->verification_method);
        $this->assertSame($payload['verification']['passed'], $session->verification_passed);
        $this->assertEquals(0.87, (float) $session->verification_score);
    }

    /** Each case also breaks the later checks it can, to pin the order. */
    public static function depositChecks(): array
    {
        return [
            'VERIFICATION_FAILED' => [
                ['verification' => ['passed' => false], 'weight_stable' => false, 'weight_g' => 0],
                'VERIFICATION_FAILED',
            ],
            'WEIGHT_UNSTABLE' => [['weight_stable' => false, 'weight_g' => 0], 'WEIGHT_UNSTABLE'],
            'NO_ITEM' => [['weight_g' => 0], 'NO_ITEM'],
            'ZERO_WEIGHT' => [['weight_g' => 9.99], 'ZERO_WEIGHT'],
            'OVER_MAX_WEIGHT' => [['weight_g' => 2000.01], 'OVER_MAX_WEIGHT'],
        ];
    }

    public function test_it_expires_an_open_session_at_request_time(): void
    {
        $this->travel(config('ewaste.session_timeout_seconds') + 1)->seconds();

        $this->deposit(self::VALID)
            ->assertStatus(410)
            ->assertJsonPath('error.code', 'SESSION_EXPIRED');

        $session = $this->session->fresh();
        $this->assertSame(SessionStatus::Expired, $session->status);
        $this->assertNotNull($session->closed_at);
        $this->assertNull($session->net_weight_g);
    }

    private function deposit(array $payload)
    {
        return $this->devicePost($this->device, "/api/v1/device/sessions/{$this->session->id}/deposit", $payload);
    }
}
