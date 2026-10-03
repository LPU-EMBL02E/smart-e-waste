<?php

namespace App\Modules\Identity\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * PUT /admin/users/{user}/password. API_Design.md §7.6, P41.
 *
 * The fallback for when email cannot be sent — an outage, or the free mail plan's
 * daily limit reached during a large import (System_Plan.md §7.2). It is also
 * how an account gets a password at all if the student never receives the link.
 */
class SetUserPasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
