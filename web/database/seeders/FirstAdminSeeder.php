<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

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
 * repository or the database.
 *
 * After seeding, that admin must do two things before the bin works at all:
 *   1. register the bin and enter its calibration values (/admin/bins);
 *   2. create the first reward rule (/admin/points-rate).
 * Until both exist every deposit is refused with NO_REWARD_RULE.
 *
 * Read the email from .env rather than hard-coding one, so the repository
 * carries no personal address.
 *
 * TODO: method body.
 */
class FirstAdminSeeder extends Seeder
{
    public function run(): void
    {
        throw new \LogicException('Not implemented.');
    }
}
