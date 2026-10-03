<?php

namespace App\Modules\Devices\Http\Requests\Device;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/device/telemetry. API_Design.md §5.3.
 *
 * One raw ultrasonic reading. The device never sends fill_percent or a
 * full/not-full judgement: the server computes both
 * (System_Plan.md §5.3 rule 5).
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
        ];
    }
}
