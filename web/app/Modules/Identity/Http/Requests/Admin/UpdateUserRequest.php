<?php

namespace App\Modules\Identity\Http\Requests\Admin;

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PUT /admin/users/{user}. API_Design.md §7.6.
 *
 * Same rules as creating, except the two unique checks ignore this user's own
 * row. Deactivating by setting status = inactive is how an account is switched
 * off; there is no delete route (P33).
 */
class UpdateUserRequest extends FormRequest
{
    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'student_number' => ['nullable', 'string', 'max:50', Rule::unique('users', 'student_number')->ignore($userId)],
            'role' => ['required', Rule::in([User::ROLE_STUDENT, User::ROLE_ADMIN])],
            'organization_id' => [
                'nullable',
                'exists:organizations,id',
                Rule::requiredIf(fn () => $this->input('role') === User::ROLE_STUDENT),
                Rule::prohibitedIf(fn () => $this->input('role') === User::ROLE_ADMIN),
            ],
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE])],
        ];
    }
}
