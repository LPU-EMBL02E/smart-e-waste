<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Support\Theme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /theme. API_Design.md §7.6 ("Choose a theme"), P52.
 */
class UpdateThemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'theme' => ['required', Rule::enum(Theme::class)],
        ];
    }
}
