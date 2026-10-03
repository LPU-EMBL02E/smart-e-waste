<?php

namespace App\Modules\Devices\Http\Requests\Admin;

use App\Modules\Devices\Models\Device;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST and PUT /admin/bins. API_Design.md §7.6 ("Register or edit a bin").
 *
 * `secret` is NOT a field here. The server generates it
 * (DeviceSecret::generate()) and shows it once on the page that follows, for
 * the admin to type into the bin's setup portal (P39).
 *
 * empty_distance_mm must be above 0 because the fill formula divides by it: a
 * zero would make every fill calculation fail.
 *
 * Saving new calibration values sets calibrated_at.
 */
class StoreDeviceRequest extends FormRequest
{
    public function rules(): array
    {
        $deviceId = $this->route('device')?->id;

        return [
            'device_code' => [
                'required', 'string', 'max:50',
                Rule::unique('devices', 'device_code')->ignore($deviceId),
            ],
            'name' => ['required', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'empty_distance_mm' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'fill_threshold_percent' => ['required', 'numeric', 'between:0,100', 'decimal:0,2'],
            'weight_offset' => ['nullable', 'numeric', 'decimal:0,4'],
            'weight_scale_factor' => ['nullable', 'numeric', 'decimal:0,6'],
            'status' => ['required', Rule::in([
                Device::STATUS_ACTIVE,
                Device::STATUS_INACTIVE,
                Device::STATUS_RETIRED,
            ])],
        ];
    }
}
