<?php

namespace App\Modules\Devices\Http\Requests\Device;

use App\Modules\Devices\Support\BinFaultCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/v1/device/telemetry. API_Design.md §5.3.
 *
 * One raw ultrasonic reading. The device never sends fill_percent or a
 * full/not-full judgement: the server computes both
 * (System_Plan.md §5.3 rule 5).
 *
 * `panel_open` and `fault_code` are required in every call; `fault_code` is
 * present but null when the bin has no fault (API_Design.md P42).
 */
class TelemetryRequest extends FormRequest
{
    /**
     * VerifyDeviceSignature has already authenticated the caller.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'distance_mm' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'panel_open' => ['required', 'boolean'],
            'fault_code' => ['present', 'nullable', Rule::enum(BinFaultCode::class)],
        ];
    }
}
