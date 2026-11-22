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
    <h1>Dashboard</h1>

    <p>Updated <span id="dashboard-generated-at">{{ Carbon::parse($summary['generated_at'])->timezone(config('ewaste.display_timezone'))->format('d M Y, g:ia') }}</span></p>

    <h2>Today</h2>

    {{-- Completed deposits since midnight; voided ones drop out. --}}
    <ul>
        <li>Deposits: <span data-summary="today.deposits">{{ $summary['today']['deposits'] }}</span></li>
        <li>Weight collected: <span data-summary="today.weight_g">{{ $summary['today']['weight_g'] }}</span> g</li>
        <li>Points awarded: <span data-summary="today.points_awarded">{{ $summary['today']['points_awarded'] }}</span></li>
    </ul>

    <h2>Waiting for an admin</h2>

    <ul>
        <li><a href="{{ route('admin.redemptions.index') }}">Pending redemptions</a>: <span data-summary="pending_redemptions">{{ $summary['pending_redemptions'] }}</span></li>
        <li><a href="{{ route('admin.sessions.review') }}">Sessions to review</a>: <span data-summary="sessions_awaiting_review">{{ $summary['sessions_awaiting_review'] }}</span></li>
    </ul>

    <h2>Bins</h2>

    {{-- Each row is built the same way by binRow() in the script below. --}}
    <div style="overflow-x: auto">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Fill</th>
                    <th>Panel</th>
                    <th>Fault</th>
                    <th>Last seen</th>
                </tr>
            </thead>
            <tbody id="dashboard-bins">
                @forelse ($summary['bins'] as $bin)
                    <tr>
                        <td>{{ $bin['name'] }}</td>
                        @if ($bin['status'] === null)
                            <td colspan="3">No reading yet</td>
                        @else
                            <td>{{ $bin['fill_percent'] }}% ({{ $bin['status'] }})</td>
                            <td>{{ $bin['panel_open'] ? 'Open' : 'Closed' }}</td>
                            <td>{{ $bin['fault_code'] ?? 'None' }}</td>
                        @endif
                        <td>{{ $bin['last_seen_at'] === null ? 'Never' : Carbon::parse($bin['last_seen_at'])->timezone(config('ewaste.display_timezone'))->format('d M Y, g:ia') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">No bins registered yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
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

            // The Blade format above: 30 Oct 2026, 9:05am.
            const formatTime = (iso) => {
                const part = Object.fromEntries(
                    timeParts.formatToParts(new Date(iso)).map(({ type, value }) => [type, value]),
                );

                return `${part.day} ${part.month} ${part.year}, ${part.hour}:${part.minute}${part.dayPeriod.toLowerCase()}`;
            };

            // textContent, never innerHTML: bin names are typed in by admins.
            const cell = (text, colSpan = 1) => {
                const td = document.createElement('td');
                td.textContent = text;
                td.colSpan = colSpan;

                return td;
            };

            const row = (...cells) => {
                const tr = document.createElement('tr');
                tr.append(...cells);

                return tr;
            };

            const binRow = (bin) => row(
                cell(bin.name),
                ...(bin.status === null
                    ? [cell('No reading yet', 3)]
                    : [
                        cell(`${bin.fill_percent}% (${bin.status})`),
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
                    ...(summary.bins.length > 0
                        ? summary.bins.map(binRow)
                        : [row(cell('No bins registered yet.', 5))]),
                );
            };

            // One request at a time: the next is scheduled only after this one
            // ends. Skipped while the tab is hidden, so a forgotten tab does
            // not keep the Pi busy.
            const poll = async () => {
                if (!document.hidden) {
                    try {
                        const response = await fetch(summaryUrl, { headers: { Accept: 'application/json' } });

                        if (response.ok) {
                            render(await response.json());
                        }
                    } catch {
                        // Offline or an unreadable reply: keep the last numbers
                        // and try again; "Updated" shows how old they are.
                    }
                }

                setTimeout(poll, pollMs);
            };

            setTimeout(poll, pollMs);
        })();
    </script>
@endsection
