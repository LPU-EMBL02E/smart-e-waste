{{--
    GET /admin — the admin dashboard. API_Design.md §7.4, §7.5;
    web/docs/DESIGN.md §7; all-pages-wireframe.html A1.

    Rendered from the same array as GET /admin/dashboard/summary, so the page is
    complete without JavaScript. resources/js/pages/admin-dashboard.js, the one
    script in the application (System_Plan.md §3), refreshes it every
    config('ewaste.dashboard_poll_seconds') seconds (P34).

    The summary's times are ISO 8601 in UTC. Blade and the script both show them
    in config('ewaste.display_timezone'), in the same format.
--}}
@use('App\Modules\Devices\Models\BinTelemetry')
@use('Illuminate\Support\Carbon')
@use('Illuminate\Support\Str')

@extends('layouts.admin')

@section('title', 'Dashboard')

@push('scripts')
    @vite('resources/js/pages/admin-dashboard.js')
@endpush

@section('content')
    <div class="section"
         data-dashboard
         data-summary-url="{{ route('admin.dashboard.summary') }}"
         data-poll-ms="{{ config('ewaste.dashboard_poll_seconds') * 1000 }}"
         data-time-zone="{{ config('ewaste.display_timezone') }}"
         data-bin-url="{{ route('admin.bins.show', ':id') }}"
         data-redemptions-url="{{ route('admin.redemptions.index') }}"
         data-review-url="{{ route('admin.sessions.review') }}">
        <x-ui.page-heading title="Dashboard" />

        {{-- Announces only a failed refresh and its recovery, never each poll (DESIGN.md §9). --}}
        <div aria-live="polite" data-dashboard-status></div>

        <section class="attention">
            <h2>Needs attention</h2>
            <ul data-attention>
                <li>
                    <a href="{{ route('admin.redemptions.index') }}">
                        {{ $summary['pending_redemptions'] }} {{ Str::plural('redemption', $summary['pending_redemptions']) }} waiting to be fulfilled
                    </a>
                </li>
                @foreach ($summary['bins'] as $bin)
                    @if ($bin['status'] === BinTelemetry::STATUS_FULL)
                        <li><a href="{{ route('admin.bins.show', $bin['id']) }}">{{ $bin['name'] }} is full (@percent($bin['fill_percent']))</a></li>
                    @endif
                    @if ($bin['fault_code'])
                        <li><a href="{{ route('admin.bins.show', $bin['id']) }}">{{ $bin['name'] }}: @faultlabel($bin['fault_code'])</a></li>
                    @endif
                @endforeach
                <li>
                    <a href="{{ route('admin.sessions.review') }}">
                        {{ $summary['sessions_awaiting_review'] }} {{ Str::plural('session', $summary['sessions_awaiting_review']) }} awaiting review
                    </a>
                </li>
            </ul>
        </section>

        <section class="section">
            <h2>Bins</h2>

            <x-ui.empty title="No bins registered yet" data-bins-empty :hidden="count($summary['bins']) > 0" />

            <x-ui.table label="Bins" data-bins-table :hidden="count($summary['bins']) === 0">
                <x-slot:head>
                    <th scope="col">Bin</th>
                    <th scope="col">Fill level</th>
                    <th scope="col">Status</th>
                    <th scope="col">Service panel</th>
                    <th scope="col">Fault</th>
                    <th scope="col">Last seen ({{ config('ewaste.display_timezone') }})</th>
                </x-slot:head>

                @foreach ($summary['bins'] as $bin)
                    @include('reporting.admin._bin-row')
                @endforeach
            </x-ui.table>

            <template data-bin-template>
                @include('reporting.admin._bin-row', ['bin' => null])
            </template>
        </section>

        <section class="section">
            <h2>Today</h2>

            {{-- Completed deposits since midnight; voided ones drop out. --}}
            <div class="tiles">
                <x-ui.tile label="Deposits"><span data-summary="today.deposits">{{ $summary['today']['deposits'] }}</span></x-ui.tile>
                <x-ui.tile label="Weight collected"><span data-summary="today.weight_g" data-format="grams">@grams($summary['today']['weight_g'])</span></x-ui.tile>
                <x-ui.tile label="Points awarded"><span data-summary="today.points_awarded" data-format="points">@points($summary['today']['points_awarded'])</span></x-ui.tile>
            </div>

            <p class="small muted">
                Updated <span data-generated-at>@datetime(Carbon::parse($summary['generated_at']))</span>
                ({{ config('ewaste.display_timezone') }})
            </p>
        </section>
    </div>
@endsection
