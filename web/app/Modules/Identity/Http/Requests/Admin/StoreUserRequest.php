<?php

namespace App\Modules\Identity\Http\Requests\Admin;

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /admin/users. API_Design.md §7.6 ("Create or edit a user").
 *
 * No password field — the account is created with users.password NULL and the
 * student sets it from the emailed link (System_Plan.md §4).
 *
 * organization_id is required for a student and MUST be null for an admin.
 * Admins having no organization is what lets the organization leaderboard group
 * on students without a NULL group appearing (System_Plan.md §5.6). An inactive
 * organization takes no new students (web/docs/ACCOUNT-MANAGEMENT.md).
 */
class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'student_number' => ['nullable', 'string', 'max:50', Rule::unique('users', 'student_number')],
            'role' => ['required', Rule::in([User::ROLE_STUDENT, User::ROLE_ADMIN])],
            'organization_id' => [
                'nullable',
                Rule::exists('organizations', 'id')->where('is_active', true),
                Rule::requiredIf(fn () => $this->input('role') === User::ROLE_STUDENT),
                Rule::prohibitedIf(fn () => $this->input('role') === User::ROLE_ADMIN),
            ],
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE])],
        ];
    }
}
