<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * POST /register. API_Design.md §7.6 ("Register"), P41, P46.
 *
 * There is no role or status field: registration only ever creates an active
 * student (AccountService::register()).
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'student_number' => ['required', 'string', 'max:50', Rule::unique('users', 'student_number')],
            'organization_id' => ['required', Rule::exists('organizations', 'id')->where('is_active', true)],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
