<?php

namespace Tests\Feature\Deposits;

use App\Modules\Deposits\Models\DepositSession;
use App\Modules\Deposits\Models\Transaction;
use App\Modules\Deposits\Support\SessionStatus;
use App\Modules\Devices\Models\BinTelemetry;
use App\Modules\Devices\Models\Device;
use App\Modules\Identity\Models\User;
use App\Modules\Rewards\Models\RewardRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\SignsDeviceRequests;
use Tests\TestCase;

/**
 * POST /api/v1/device/sessions. API_Design.md §5.4 and §4.3, P6, P12, P13.
 */
class OpenSessionTest extends TestCase
{
    use RefreshDatabase;
    use SignsDeviceRequests;

    private const PATH = '/api/v1/device/sessions';

    private Device $device;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->device = Device::factory()->create();
        $this->student = User::factory()->create(['first_name' => 'Juan']);
    }

    public function test_it_opens_a_session_from_a_valid_token(): void
    {
        RewardRule::factory()->create();
        $this->freezeSecond();

        $response = $this->scan(['fw' => '1.0.3'])->assertCreated();

        $session = DepositSession::sole();
        $response->assertExactJson([
            'session_id' => $session->id,
            'expires_in_s' => config('ewaste.session_timeout_seconds'),
            'user' => ['display_name' => 'Juan'],
        ]);
        $this->assertSame(SessionStatus::Open, $session->status);
        $this->assertTrue($session->user->is($this->student));
        $this->assertTrue($session->expires_at->equalTo(now()->addSeconds(config('ewaste.session_timeout_seconds'))));
        $this->assertTrue($this->student->qrCredential->fresh()->last_used_at->equalTo(now()));
        $this->assertSame('1.0.3', $this->device->fresh()->firmware_version);
    }

    /**
     * Each case breaks its own check AND every later one, so the reply proves
     * which check runs first. A refused scan writes nothing.
     */
    #[DataProvider('openChecks')]
    public function test_it_refuses_each_open_check_in_order(int $firstFailing, string $code, int $status): void
    {
        $rule = RewardRule::factory()->dailyCap(40)->create();
        $breaks = [
            fn () => null,   // INVALID_QR: an unknown token is sent below
            fn () => $this->student->update(['status' => User::STATUS_INACTIVE]),
            fn () => BinTelemetry::factory()->for($this->device)->full()->create(),
            fn () => $rule->update(['effective_to' => now()->subMinute()]),
            fn () => Transaction::factory()
                ->for(DepositSession::factory()->completed()->for($this->student), 'session')
                ->create(['reward_rule_id' => $rule->id, 'points_awarded' => 40]),
            fn () => DepositSession::factory()->for($this->student)->create(),   // on another bin
        ];

        foreach (array_slice($breaks, $firstFailing) as $break) {
            $break();
        }

        $this->scan($firstFailing === 0 ? ['qr_token' => 'NOT-A-TOKEN'] : [])
            ->assertStatus($status)
            ->assertJsonPath('error.code', $code);

        $this->assertSame(0, $this->device->depositSessions()->count());
        $this->assertNull($this->student->qrCredential->fresh()->last_used_at);
    }

    public static function openChecks(): array
    {
        return [
            'INVALID_QR' => [0, 'INVALID_QR', 401],
            'USER_INACTIVE' => [1, 'USER_INACTIVE', 403],
            'BIN_FULL' => [2, 'BIN_FULL', 409],
            'NO_REWARD_RULE' => [3, 'NO_REWARD_RULE', 503],
            'DAILY_LIMIT_REACHED' => [4, 'DAILY_LIMIT_REACHED', 429],
            'DUPLICATE_TRANSACTION' => [5, 'DUPLICATE_TRANSACTION', 409],
        ];
    }

    public function test_it_cancels_the_bins_earlier_open_session(): void
    {
        RewardRule::factory()->create();
        $earlier = DepositSession::factory()->for($this->device)->create();

        $this->scan()->assertCreated();

        $earlier->refresh();
        $this->assertSame(SessionStatus::Cancelled, $earlier->status);
        $this->assertNotNull($earlier->closed_at);
        $this->assertSame(1, DepositSession::where('status', SessionStatus::Open)->count());
    }

    public function test_it_leaves_an_accepted_session_alone_on_a_new_scan(): void
    {
        RewardRule::factory()->create();
        $accepted = DepositSession::factory()->for($this->device)->for($this->student)->accepted()->create();

        $this->scan()->assertCreated();

        $this->assertSame(SessionStatus::Accepted, $accepted->fresh()->status);
    }

    private function scan(array $body = [])
    {
        return $this->devicePost($this->device, self::PATH, $body + [
            'qr_token' => $this->student->qrCredential->token,
        ]);
    }
}
