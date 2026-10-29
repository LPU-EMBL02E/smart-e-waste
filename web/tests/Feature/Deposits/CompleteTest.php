<?php

namespace Tests\Feature\Deposits;

use App\Modules\Deposits\Models\DepositSession;
use App\Modules\Deposits\Models\Transaction;
use App\Modules\Deposits\Support\FailureCode;
use App\Modules\Deposits\Support\SessionStatus;
use App\Modules\Devices\Models\Device;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Support\NotificationType;
use App\Modules\Rewards\Models\PointLedgerEntry;
use App\Modules\Rewards\Models\RewardRule;
use App\Modules\Rewards\Support\LedgerEntryType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsDeviceRequests;
use Tests\TestCase;

/**
 * POST /api/v1/device/sessions/{id}/complete. API_Design.md §5.6, §4.1, P10,
 * P26; System_Plan.md §5.3 rule 6 and §5.7 rules 2, 4 and 6.
 *
 * The accepted session weighs 184.6 g; the factory rule gives 0.1 points per
 * gram, so it earns 18.
 */
class CompleteTest extends TestCase
{
    use RefreshDatabase;
    use SignsDeviceRequests;

    private Device $device;

    private User $student;

    private RewardRule $rule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule = RewardRule::factory()->create();
        $this->device = Device::factory()->create();
        $this->student = User::factory()->create();
    }

    public function test_it_credits_points_on_complete(): void
    {
        $session = $this->acceptedSession();

        $response = $this->complete($session, ['actuator_ok' => true, 'cycle_ms' => 3200])->assertOk();

        $transaction = Transaction::sole();
        $response->assertExactJson(['transaction_id' => $transaction->id, 'points_awarded' => 18, 'balance' => 18]);
        $this->assertMatchesRegularExpression('/^TXN-\d{8}-[A-Z0-9]{6}$/', $transaction->transaction_code);
        $this->assertSame(Transaction::STATUS_COMPLETED, $transaction->status);
        $this->assertSame($this->rule->id, $transaction->reward_rule_id);

        $entry = PointLedgerEntry::sole();
        $this->assertSame(LedgerEntryType::Earn, $entry->entry_type);
        $this->assertSame(18, $entry->points_delta);
        $this->assertSame($this->student->id, $entry->user_id);

        $session->refresh();
        $this->assertSame(SessionStatus::Completed, $session->status);
        $this->assertTrue($session->actuator_ok);
        $this->assertSame(3200, $session->actuator_cycle_ms);
        $this->assertNotNull($session->closed_at);

        $this->assertSame(1, $this->student->notifications()->where('type', NotificationType::DepositCompleted)->count());
    }

    public function test_it_credits_points_once_when_complete_is_repeated(): void
    {
        $session = $this->acceptedSession();

        $first = $this->complete($session, ['actuator_ok' => true])->assertOk()->json();
        $second = $this->complete($session, ['actuator_ok' => true])->assertOk()->json();

        $this->assertSame($first, $second);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('point_ledger', 1);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_it_fails_the_session_without_points_when_the_actuator_fails(): void
    {
        $session = $this->acceptedSession();

        $this->complete($session, ['actuator_ok' => false, 'cycle_ms' => 9000])
            ->assertOk()
            ->assertExactJson(['transaction_id' => null, 'points_awarded' => 0, 'balance' => 0]);

        $session->refresh();
        $this->assertSame(SessionStatus::Failed, $session->status);
        $this->assertSame(FailureCode::ACTUATOR_FAILED, $session->failure_code);
        $this->assertFalse($session->actuator_ok);
        $this->assertNotNull($session->closed_at);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('point_ledger', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_it_accepts_complete_after_the_review_threshold(): void
    {
        $session = $this->acceptedSession();
        $this->travel(config('ewaste.review_threshold_minutes') + 60)->minutes();

        $this->complete($session, ['actuator_ok' => true])
            ->assertOk()
            ->assertJsonPath('points_awarded', 18);

        $this->assertSame(SessionStatus::Completed, $session->fresh()->status);
    }

    public function test_it_uses_the_rule_the_session_opened_under(): void
    {
        $session = $this->acceptedSession();

        // An admin saves a richer rule while the item is on the platform.
        $this->travel(5)->seconds();
        $this->rule->update(['effective_to' => now()]);
        RewardRule::factory()->create(['points_per_gram' => 1, 'effective_from' => now()]);

        $this->complete($session, ['actuator_ok' => true])->assertJsonPath('points_awarded', 18);

        $this->assertSame($this->rule->id, Transaction::sole()->reward_rule_id);
    }

    private function acceptedSession(): DepositSession
    {
        return DepositSession::factory()->for($this->device)->for($this->student)->accepted()->create();
    }

    private function complete(DepositSession $session, array $body)
    {
        return $this->devicePost($this->device, "/api/v1/device/sessions/{$session->id}/complete", $body);
    }
}
