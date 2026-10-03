<?php

namespace App\Modules\Identity\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /admin/users/import. API_Design.md §7.6 ("CSV import"), P36.
 *
 * Columns, with a header row:
 *   student_number, first_name, last_name, email, organization_code
 *
 * Every row becomes a student, and organization_code must match an existing
 * organizations.code.
 *
 * All rows or none: the file is validated as a whole, and if any row is invalid
 * nothing is imported and the page lists the rows and their errors. Each created
 * user also gets its user_qr_credentials row.
 *
 * The import sends NO email. Set-password emails go out afterwards from the user
 * list, which keeps a large import inside one request with no queue and inside
 * the mail service's daily limit (System_Plan.md §7.2).
 *
 * TODO(team): API_Design.md §10 question 6 — whether a bulk "send set-password
 * emails" action is needed after an import, or sending from each user's row is
 * enough. The free mail plan allows 100 a day.
 */
class ImportUsersRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }
}
