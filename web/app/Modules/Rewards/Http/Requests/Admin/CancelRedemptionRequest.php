<?php

namespace App\Modules\Rewards\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /admin/redemptions/{redemption}/cancel. API_Design.md §7.6 ("Fulfil or
 * cancel a redemption").
 *
 * The reason becomes the REVERSAL ledger row's description, so the ledger says
 * why the points came back. Required, and at most 255 characters to fit
 * point_ledger.description (Database_Schema.md §9). The field is the owner's
 * decision for Track C, question 1; docs/ does not define it yet.
 */
class CancelRedemptionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'cancel_reason' => ['required', 'string', 'max:255'],
        ];
    }
}
