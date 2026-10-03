<?php

namespace App\Modules\Deposits\Http\Requests\Device;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/device/sessions/{id}/deposit. API_Design.md §5.5.
 *
 * The calibrated net weight and the camera's verdict. All six values are stored
 * on the session whether the deposit is accepted or rejected, because they are
 * the evidence for the attempt (P22).
 *
 * `verification` is kept as a nested object so the verification method can be
 * swapped — another camera or another sensor — without the server changing
 * (System_Plan.md §8.1).
 */
class RecordDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'weight_g' => ['required', 'numeric', 'decimal:0,2'],
            'weight_stable' => ['required', 'boolean'],
            'samples' => ['required', 'integer', 'min:0'],
            'verification' => ['required', 'array'],
            'verification.method' => ['required', 'string', 'max:50'],
            'verification.passed' => ['required', 'boolean'],
            'verification.score' => ['nullable', 'numeric', 'between:0,1', 'decimal:0,4'],
        ];
    }
}
