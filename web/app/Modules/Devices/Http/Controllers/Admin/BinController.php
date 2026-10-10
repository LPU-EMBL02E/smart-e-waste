<?php

namespace App\Modules\Devices\Http\Controllers\Admin;

use App\Modules\Devices\Http\Requests\Admin\StoreDeviceRequest;
use App\Modules\Devices\Models\Device;
use App\Modules\Devices\Services\BinService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin bin monitoring and registration. API_Design.md §7.4.
 *
 *   GET  /admin/bins                  list with current fill level
 *   GET  /admin/bins/create           register form
 *   POST /admin/bins                  register and issue the first secret
 *   GET  /admin/bins/{device}         one bin and its fill history
 *   GET  /admin/bins/{device}/edit    edit form, including calibration
 *   PUT  /admin/bins/{device}         save
 *   POST /admin/bins/{device}/secret  issue a new secret
 *
 * The secret is shown ONCE, on the page after registration or a new-secret
 * request. It is encrypted at rest and never displayed again, so an admin who
 * loses it issues a new one — which stops the old one at once and leaves the
 * bin unable to talk to the server until the new one is entered in its setup
 * portal (P39).
 *
 * No delete route: a bin is retired with its status, because sessions and
 * telemetry reference it (P33).
 *
 * TODO: all method bodies.
 */
class BinController
{
    public function __construct(private BinService $bins) {}

    public function index(): View
    {
        throw new \LogicException('Not implemented.');
    }

    public function create(): View
    {
        throw new \LogicException('Not implemented.');
    }

    /** Redirects to a page that shows the plain secret once. */
    public function store(StoreDeviceRequest $request): RedirectResponse
    {
        throw new \LogicException('Not implemented.');
    }

    public function show(Device $device): View
    {
        throw new \LogicException('Not implemented.');
    }

    public function edit(Device $device): View
    {
        throw new \LogicException('Not implemented.');
    }

    public function update(StoreDeviceRequest $request, Device $device): RedirectResponse
    {
        throw new \LogicException('Not implemented.');
    }

    public function rotateSecret(Device $device): RedirectResponse
    {
        throw new \LogicException('Not implemented.');
    }
}
