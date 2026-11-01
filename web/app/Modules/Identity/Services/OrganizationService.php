<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\Organization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

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

    /**
     * The admin organization list. API_Design.md §7.4.
     *
     * @return LengthAwarePaginator<int, Organization>
     */
    public function list(): LengthAwarePaginator
    {
        return Organization::orderBy('name')->paginate();
    }

    /**
     * For a form's organization list. Not paginated: a school has few of them.
     *
     * @param  int|null  $currentId  the student's own organization, kept in the
     *                               list if inactive so an edit can leave it alone
     * @return Collection<int, Organization>
     */
    public function activeOptions(?int $currentId = null): Collection
    {
        return Organization::active()
            ->when($currentId, fn (Builder $query) => $query->orWhere('id', $currentId))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
