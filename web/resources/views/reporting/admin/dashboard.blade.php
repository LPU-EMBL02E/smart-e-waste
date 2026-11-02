{{--
    GET /admin — the admin dashboard. API_Design.md §7.4 and §7.5.

    Rendered from the same array as GET /admin/dashboard/summary, so the page is
    complete without JavaScript. The script at the bottom is the one script in
    the application (System_Plan.md §3): it refreshes these numbers every
    config('ewaste.dashboard_poll_seconds') seconds (P34).

    The summary's times are ISO 8601 in UTC. Blade and the script both show them
    in config('ewaste.display_timezone'), in the same format.
--}}
@use('Illuminate\Support\Carbon')

@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    <div class="page-heading">
        <h1>Dashboard</h1>
        <p>Monitor campus collection and the work that needs your attention.</p>
    </div>
    <div class="section-heading">
        <h2>Collection summary</h2>
        <p class="refresh-note">Updated <span
                id="dashboard-generated-at">{{ Carbon::parse($summary['generated_at'])->timezone(config('ewaste.display_timezone'))->format('d M Y, g:ia') }}</span>
            ({{ config('ewaste.display_timezone') }}).</p>
    </div>
    <p id="dashboard-refresh-feedback" class="refresh-feedback" role="status" hidden></p>

    <dl class="metric-grid">
        <div class="metric">
            <dt>Completed deposits</dt>
            <dd data-summary="today.deposits">{{ $summary['today']['deposits'] }}</dd>
            <dd class="metric-note">Today, since midnight</dd>
        </div>
        <div class="metric">
            <dt>Weight collected</dt>
            <dd><span data-summary="today.weight_g">{{ $summary['today']['weight_g'] }}</span> <small>g</small></dd>
            <dd class="metric-note">Today's collected weight</dd>
        </div>
        <div class="metric">
            <dt>Points awarded</dt>
            <dd data-summary="today.points_awarded">{{ $summary['today']['points_awarded'] }}</dd>
            <dd class="metric-note">Today's credited points</dd>
        </div>
        <div class="metric">
            <dt><a href="{{ route('admin.redemptions.index') }}">Pending redemptions</a></dt>
            <dd data-summary="pending_redemptions">{{ $summary['pending_redemptions'] }}</dd>
            <dd class="metric-note">Waiting for fulfillment</dd>
        </div>
        <div class="metric">
            <dt><a href="{{ route('admin.sessions.review') }}">Sessions to review</a></dt>
            <dd data-summary="sessions_awaiting_review">{{ $summary['sessions_awaiting_review'] }}</dd>
            <dd class="metric-note">Awaiting investigation</dd>
        </div>
    </dl>

    <section class="dashboard-panel" aria-labelledby="bins-heading">
        <div class="panel-heading">
            <h2 id="bins-heading">Bins</h2>
            <a class="text-action" href="{{ route('admin.bins.index') }}">View all <span aria-hidden="true">→</span></a>
        </div>
        <div class="panel-body">
            {{-- Each row is built the same way by binRow() in the script below. --}}
            <div class="table-region" role="region" tabindex="0" aria-label="Bin status">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col" class="numeric">Fill</th>
                            <th scope="col">Panel</th>
                            <th scope="col">Fault</th>
                            <th scope="col">Last seen</th>
                        </tr>
                    </thead>
                    <tbody id="dashboard-bins">
                        @forelse ($summary['bins'] as $bin)
                            <tr>
                                <td>{{ $bin['name'] }}</td>
                                @if ($bin['status'] === null)
                                    <td colspan="3">No reading yet</td>
                                @else
                                    <td class="numeric">{{ $bin['fill_percent'] }}% ({{ $bin['status'] }})</td>
                                    <td>{{ $bin['panel_open'] ? 'Open' : 'Closed' }}</td>
                                    <td>{{ $bin['fault_code'] ?? 'None' }}</td>
                                @endif
                                <td>{{ $bin['last_seen_at'] === null ? 'Never' : Carbon::parse($bin['last_seen_at'])->timezone(config('ewaste.display_timezone'))->format('d M Y, g:ia') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">No bins registered yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    <div class="quick-actions">
        <a class="button secondary" href="{{ route('admin.transactions.index') }}">Go to transactions</a>
        <a class="button secondary" href="{{ route('admin.redemptions.index') }}">Go to redemptions</a>
        <a class="button secondary" href="{{ route('admin.sessions.review') }}">Go to sessions in review</a>
    </div>

    <script>
        (() => {
            const summaryUrl = @json(route('admin.dashboard.summary'));
            const pollMs = @json(config('ewaste.dashboard_poll_seconds') * 1000);
            const timeParts = new Intl.DateTimeFormat('en-US', {
                timeZone: @json(config('ewaste.display_timezone')),
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true,
            });
            const bins = document.getElementById('dashboard-bins');
            const refreshFeedback = document.getElementById('dashboard-refresh-feedback');

            // The Blade format above: 30 Oct 2026, 9:05am.
            const formatTime = (iso) => {
                const part = Object.fromEntries(
                    timeParts.formatToParts(new Date(iso)).map(({
                        type,
                        value
                    }) => [type, value]),
                );

                return `${part.day} ${part.month} ${part.year}, ${part.hour}:${part.minute}${part.dayPeriod.toLowerCase()}`;
            };

            // textContent, never innerHTML: bin names are typed in by admins.
            const cell = (text, colSpan = 1, className = '') => {
                const td = document.createElement('td');
                td.textContent = text;
                td.colSpan = colSpan;
                td.className = className;

                return td;
            };

            const row = (...cells) => {
                const tr = document.createElement('tr');
                tr.append(...cells);

                return tr;
            };

            const binRow = (bin) => row(
                cell(bin.name),
                ...(bin.status === null ? [cell('No reading yet', 3)] : [
                    cell(`${bin.fill_percent}% (${bin.status})`, 1, 'numeric'),
                    cell(bin.panel_open ? 'Open' : 'Closed'),
                    cell(bin.fault_code ?? 'None'),
                ]),
                cell(bin.last_seen_at === null ? 'Never' : formatTime(bin.last_seen_at)),
            );

            const render = (summary) => {
                document.getElementById('dashboard-generated-at').textContent = formatTime(summary.generated_at);

                document.querySelectorAll('[data-summary]').forEach((element) => {
                    element.textContent = element.dataset.summary
                        .split('.')
                        .reduce((value, key) => value[key], summary);
                });

                bins.replaceChildren(
                    ...(summary.bins.length > 0 ?
                        summary.bins.map(binRow) : [row(cell('No bins registered yet.', 5))]),
                );
            };

            // One request at a time: the next is scheduled only after this one
            // ends. Skipped while the tab is hidden, so a forgotten tab does
            // not keep the Pi busy.
            const poll = async () => {
                if (!document.hidden) {
                    try {
                        const response = await fetch(summaryUrl, {
                            headers: {
                                Accept: 'application/json'
                            }
                        });

                        if (!response.ok) throw new Error('Summary unavailable');
                        render(await response.json());
                        refreshFeedback.hidden = true;
                    } catch {
                        // Preserve the last confirmed readings; DESIGN.md §7.
                        refreshFeedback.textContent =
                            'Refresh unavailable. Showing the last update; retrying automatically.';
                        refreshFeedback.hidden = false;
                    }
                }

                setTimeout(poll, pollMs);
            };

            setTimeout(poll, pollMs);
        })();
    </script>
@endsection
