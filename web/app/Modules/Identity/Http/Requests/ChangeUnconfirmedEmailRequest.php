<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PUT /email/verify. An unconfirmed student corrects the email they registered
 * with. Same email rule as registration (API_Design.md §7.6, "Register"); the
 * student's own current address counts as free, so re-entering it just sends
 * the link again.
 */
class ChangeUnconfirmedEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user())],
        ];
    }
}
