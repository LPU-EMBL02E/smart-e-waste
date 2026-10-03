<?php

namespace App\Modules\Identity\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST and PUT /admin/organizations. API_Design.md §7.6.
 *
 * `code` is what the CSV import matches on (organization_code), so it is unique
 * and ignores this row when editing.
 */
class StoreOrganizationRequest extends FormRequest
{
    public function rules(): array
    {
        $organizationId = $this->route('organization')?->id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:50', Rule::unique('organizations', 'code')->ignore($organizationId)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
