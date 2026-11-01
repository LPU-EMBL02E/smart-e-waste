<?php

namespace Database\Seeders;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\AccountService;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * The first admin account. System_Plan.md §7, step 5.
 *
 * Registration only ever creates students, so without this seeder no admin
 * exists on a fresh install and nothing else can be set up.
 *
 * It creates ONE admin: role admin, organization_id NULL, status active, and a
 * user_qr_credentials row (every user has one), with email_verified_at set
 * like any admin-created account (API_Design.md P47). It does NOT set a password —
 * that follows the same path as any other account, through the set-password
 * email or an admin-set password, so no default password ever exists in the
 * repository or the database. On a fresh install that email is read from the log
 * (web/docs/ACCOUNT-MANAGEMENT.md, "First-admin activation"; SETUP.md).
 *
 * After seeding, that admin must do two things before the bin works at all:
 *   1. register the bin and enter its calibration values (/admin/bins);
 *   2. create the first reward rule (/admin/points-rate).
 * Until both exist every deposit is refused with NO_REWARD_RULE.
 *
 * Read the email from .env rather than hard-coding one, so the repository
 * carries no personal address.
 */
class FirstAdminSeeder extends Seeder
{
    public function run(AccountService $accounts): void
    {
        // Safe to run twice: this only ever sets up an install with no admin.
        if (User::where('role', User::ROLE_ADMIN)->exists()) {
            $this->command?->warn('An admin already exists, so none was created.');

            return;
        }

        $admin = config('ewaste.first_admin');

        if (blank($admin['email'])) {
            throw new RuntimeException('Set EWASTE_FIRST_ADMIN_EMAIL in .env before seeding the first admin.');
        }

        $accounts->createByAdmin([
            'first_name' => $admin['first_name'],
            'last_name' => $admin['last_name'],
            'email' => $admin['email'],
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->command?->info("Created {$admin['email']}. Set its password at /forgot-password (see SETUP.md).");
    }
}
