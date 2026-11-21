<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * PUT /profile/password. A user changes their own password. API_Design.md §7.6
 * ("Change own password"), P41, P50.
 *
 * Its errors go in their own bag: the profile page also has the details form,
 * which has a current_password field too, and each message belongs beside the
 * form that was sent.
 */
class UpdatePasswordRequest extends FormRequest
{
    protected $errorBag = 'password';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
