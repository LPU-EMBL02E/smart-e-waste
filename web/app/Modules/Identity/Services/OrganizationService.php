<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\Organization;

/**
 * Organization writes. API_Design.md §7.4 and §7.6, System_Plan.md §4.
 *
 * No delete: an organization is switched off with is_active (P33). It keeps its
 * students but takes no new ones, which the FormRequests and
 * AccountService::import() check.
 */
class OrganizationService
{
    public function create(array $data): Organization
    {
        return Organization::create($data);
    }

    public function update(Organization $organization, array $data): Organization
    {
        $organization->update($data);

        return $organization;
    }
}
