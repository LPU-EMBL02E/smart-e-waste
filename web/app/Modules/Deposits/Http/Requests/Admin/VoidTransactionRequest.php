<?php

namespace App\Modules\Deposits\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /admin/transactions/{transaction}/void. API_Design.md §7.6, P41.
 *
 * A reason is required: voiding is one of the three controls against a heavy
 * non-e-waste deposit earning points, and the report has to be able to say why
 * each void happened (System_Plan.md §5.7, "Known limitation").
 */
class VoidTransactionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'void_reason' => ['required', 'string', 'max:500'],
        ];
    }
}
