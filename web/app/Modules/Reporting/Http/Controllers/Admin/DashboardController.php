<?php

namespace App\Modules\Reporting\Http\Controllers\Admin;

use App\Modules\Reporting\Services\ReportingQueries;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * The admin dashboard. API_Design.md §7.4 and §7.5.
 *
 *   GET /admin                      the page
 *   GET /admin/dashboard/summary    live summary, JSON
 *
 * The summary is the ONE JSON web route in the application. It uses the admin's
 * normal session and CSRF-protected web middleware, NOT the device signature
 * scheme — the two are unrelated and must not be confused.
 *
 * It is polled by one small vanilla-JS script every
 * config('ewaste.dashboard_poll_seconds') seconds. Everything else in the
 * dashboard is plain Blade: fetch()-driven CRUD and Chart.js were cut on
 * purpose (System_Plan.md §3).
 *
 * Times in the JSON are ISO 8601 in UTC; the script converts them for display.
 *
 * TODO: all method bodies.
 */
class DashboardController
{
    public function __construct(private ReportingQueries $reporting)
    {
    }

    public function index(): View
    {
        throw new \LogicException('Not implemented.');
    }

    public function summary(): JsonResponse
    {
        // TODO: return new JsonResponse($this->reporting->dashboardSummary())
        throw new \LogicException('Not implemented.');
    }
}
