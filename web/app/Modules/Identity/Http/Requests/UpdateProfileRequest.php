<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PUT /profile. A user changes their own details. API_Design.md §7.6 ("Change
 * own details"), P50.
 *
 * A student's student number and organization follow the registration rules
 * (P46), except that the unique check ignores their own row and they may stay in
 * an organization that has since been switched off. Admins have neither
 * (System_Plan.md §5.6). There is no role or status field.
 *
 * The current password is asked for only when the email changes: with a new
 * address, a session left open could otherwise take the account over through
 * "Forgot password".
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();

        $rules = [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'current_password' => $this->input('email') !== $user->email
                ? ['required', 'current_password']
                : ['exclude'],
        ];

        if ($user->isAdmin()) {
            return [...$rules, 'student_number' => ['prohibited'], 'organization_id' => ['prohibited']];
        }

        return [
            ...$rules,
            'student_number' => ['required', 'string', 'max:50', Rule::unique('users', 'student_number')->ignore($user)],
            'organization_id' => [
                'required',
                Rule::exists('organizations', 'id')->where(
                    fn (Builder $query) => $query->where('is_active', true)->orWhere('id', $user->organization_id),
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Enter your current password to change your email.',
        ];
    }
}
