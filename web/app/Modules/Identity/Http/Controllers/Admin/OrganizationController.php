<?php

namespace App\Modules\Identity\Http\Controllers\Admin;

use App\Modules\Identity\Http\Requests\Admin\StoreOrganizationRequest;
use App\Modules\Identity\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin organization management. API_Design.md §7.4.
 *
 * No delete route: an organization is switched off with is_active, because
 * users reference it (P33).
 *
 * TODO: all method bodies.
 */
class OrganizationController
{
    public function index(): View
    {
        throw new \LogicException('Not implemented.');
    }

    public function create(): View
    {
        throw new \LogicException('Not implemented.');
    }

    public function store(StoreOrganizationRequest $request): RedirectResponse
    {
        throw new \LogicException('Not implemented.');
    }

    public function edit(Organization $organization): View
    {
        throw new \LogicException('Not implemented.');
    }

    public function update(StoreOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        throw new \LogicException('Not implemented.');
    }
}
