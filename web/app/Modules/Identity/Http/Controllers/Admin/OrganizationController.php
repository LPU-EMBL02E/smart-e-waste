<?php

namespace App\Modules\Identity\Http\Controllers\Admin;

use App\Modules\Identity\Http\Requests\Admin\StoreOrganizationRequest;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Services\OrganizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin organization management. API_Design.md §7.4.
 *
 * No delete route: an organization is switched off with is_active, because
 * users reference it (P33).
 */
class OrganizationController
{
    public function __construct(private OrganizationService $organizations) {}

    public function index(): View
    {
        return view('identity.admin.organizations.index', ['organizations' => $this->organizations->list()]);
    }

    public function create(): View
    {
        return view('identity.admin.organizations.create', ['organization' => new Organization(['is_active' => true])]);
    }

    public function store(StoreOrganizationRequest $request): RedirectResponse
    {
        $this->organizations->create($request->validated());

        return redirect()->route('admin.organizations.index')->with('status', 'Organization created.');
    }

    public function edit(Organization $organization): View
    {
        return view('identity.admin.organizations.edit', ['organization' => $organization]);
    }

    public function update(StoreOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        $this->organizations->update($organization, $request->validated());

        return redirect()->route('admin.organizations.index')->with('status', 'Organization saved.');
    }
}
