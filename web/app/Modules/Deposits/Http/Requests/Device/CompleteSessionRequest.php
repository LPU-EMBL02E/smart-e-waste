<?php

namespace App\Modules\Deposits\Http\Requests\Device;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/device/sessions/{id}/complete. API_Design.md §5.6.
 *
 * `actuator_ok` is true only when the two limit switches confirm the transfer.
 * Without them the confirmation means nothing (System_Plan.md §2), and this
 * field is what the whole points rule rests on: credit comes last, after both
 * verification and a confirmed transfer.
 */
class CompleteSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'actuator_ok' => ['required', 'boolean'],
            'cycle_ms' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
