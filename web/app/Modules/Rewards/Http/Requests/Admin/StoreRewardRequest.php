<?php

namespace App\Modules\Rewards\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST and PUT /admin/rewards. API_Design.md §7.6, P41.
 *
 * Pricing guidance (System_Plan.md §5.7): decide how many grams should earn the
 * cheapest reward, then points_cost = that weight × points_per_gram, and price
 * the others relative to it.
 *
 * Changing a price never affects past redemptions: each one stores the cost it
 * was redeemed at.
 */
class StoreRewardRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'points_cost' => ['required', 'integer', 'min:1'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
