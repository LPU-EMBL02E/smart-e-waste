<?php

namespace App\Modules\Rewards\Http\Requests;

use App\Modules\Rewards\Models\Reward;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /redemptions. API_Design.md §7.6 ("Redeem a reward").
 *
 * The reward must be active. The balance and the stock are NOT checked here:
 * both are checked inside RedemptionService's database transaction, under a row
 * lock and a guarded UPDATE, because a check in validation would be a race
 * (System_Plan.md §6.1).
 */
class StoreRedemptionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'reward_id' => [
                'required',
                Rule::exists('rewards', 'id')->where('is_active', true),
            ],
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /** The reward being redeemed. RedemptionService re-reads it under its lock. */
    public function reward(): Reward
    {
        return Reward::findOrFail($this->validated('reward_id'));
    }
}
