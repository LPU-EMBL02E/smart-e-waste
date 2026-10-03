<?php

namespace App\Modules\Deposits\Http\Requests\Device;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/device/sessions. API_Design.md §5.4.
 */
class OpenSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // VerifyDeviceSignature authenticated the caller
    }

    public function rules(): array
    {
        return [
            // The scanned token. Max 64 = user_qr_credentials.token.
            'qr_token' => ['required', 'string', 'max:64'],
            // Firmware version, saved to devices.firmware_version (P6).
            'fw' => ['nullable', 'string', 'max:30'],
        ];
    }
}
