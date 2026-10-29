<?php

namespace App\Modules\Devices\Http\Controllers\Admin;

use App\Modules\Devices\Http\Requests\Admin\StoreDeviceRequest;
use App\Modules\Devices\Models\Device;
use App\Modules\Devices\Services\BinService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
 */
class BinController
{
    public function __construct(private BinService $bins) {}

    public function index(): View
    {
        return view('devices.admin.bins.index', ['devices' => $this->bins->list()]);
    }

    public function create(): View
    {
        return view('devices.admin.bins.create', ['device' => new Device]);
    }

    /** Redirects to a page that shows the plain secret once. */
    public function store(StoreDeviceRequest $request): RedirectResponse
    {
        [$device, $secret] = $this->bins->register($request->validated());

        return $this->showSecretOnce($device, $secret);
    }

    /**
     * One bin and its fill history. Straight after registration or a new
     * secret, the flashed secret is shown instead, on this one request only.
     */
    public function show(Request $request, Device $device): View
    {
        if ($request->session()->has('secret')) {
            return view('devices.admin.bins.secret', [
                'device' => $device,
                'secret' => $request->session()->get('secret'),
            ]);
        }

        return view('devices.admin.bins.show', [
            'device' => $device,
            'latest' => $this->bins->latestReading($device),
            'readings' => $this->bins->readings($device),
        ]);
    }

    public function edit(Device $device): View
    {
        return view('devices.admin.bins.edit', ['device' => $device]);
    }

    public function update(StoreDeviceRequest $request, Device $device): RedirectResponse
    {
        $this->bins->update($device, $request->validated());

        return redirect()->route('admin.bins.show', $device)->with('status', 'Bin saved.');
    }

    public function rotateSecret(Device $device): RedirectResponse
    {
        return $this->showSecretOnce($device, $this->bins->rotateSecret($device));
    }

    /**
     * Flash data lives for exactly the next request, so the secret is on screen
     * once and gone on reload (P39).
     */
    private function showSecretOnce(Device $device, string $secret): RedirectResponse
    {
        return redirect()->route('admin.bins.show', $device)->with('secret', $secret);
    }
}
