<?php

namespace Database\Factories\Deposits;

use App\Modules\Deposits\Models\DepositSession;
use App\Modules\Deposits\Support\FailureCode;
use App\Modules\Deposits\Support\SessionStatus;
use App\Modules\Devices\Models\Device;
use App\Modules\Identity\Models\User;
use App\Support\Device\DeviceErrorCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Deposit sessions for tests, one state per status. Database_Schema.md §6,
 * API_Design.md §4.1.
 *
 * The default is an OPEN session that has not expired. Every state past OPEN
 * carries the evidence that state would have recorded (P22), and every final
 * state has closed_at (P11).
 *
 * @extends Factory<DepositSession>
 */
class DepositSessionFactory extends Factory
{
    protected $model = DepositSession::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'device_id' => Device::factory(),
            'status' => SessionStatus::Open,
            'expires_at' => now()->addSeconds(config('ewaste.session_timeout_seconds')),
        ];
    }

    /** Still OPEN, but past expires_at and not yet swept (P12). */
    public function stale(): static
    {
        return $this->state(['expires_at' => now()->subSecond()]);
    }

    public function accepted(): static
    {
        return $this->withDeposit()->state(['status' => SessionStatus::Accepted]);
    }

    public function rejected(DeviceErrorCode $code = DeviceErrorCode::VerificationFailed): static
    {
        return $this->withDeposit()->closed(SessionStatus::Rejected)->state([
            'failure_code' => $code->value,
            'verification_passed' => $code !== DeviceErrorCode::VerificationFailed,
        ]);
    }

    public function completed(): static
    {
        return $this->withDeposit()->closed(SessionStatus::Completed)->state([
            'actuator_ok' => true,
            'actuator_cycle_ms' => 3200,
        ]);
    }

    public function failed(): static
    {
        return $this->withDeposit()->closed(SessionStatus::Failed)->state([
            'failure_code' => FailureCode::ACTUATOR_FAILED,
            'actuator_ok' => false,
        ]);
    }

    public function expired(): static
    {
        return $this->closed(SessionStatus::Expired)->state([
            'expires_at' => now()->subSecond(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->closed(SessionStatus::Cancelled);
    }

    /** The values POST .../deposit stores (API_Design.md §5.5 example). */
    private function withDeposit(): static
    {
        return $this->state([
            'net_weight_g' => 184.6,
            'sample_count' => 20,
            'is_weight_stable' => true,
            'verification_method' => 'cam_diff',
            'verification_passed' => true,
            'verification_score' => 0.87,
        ]);
    }

    private function closed(SessionStatus $status): static
    {
        return $this->state(['status' => $status, 'closed_at' => now()]);
    }
}
