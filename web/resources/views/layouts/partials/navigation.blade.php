{{-- Presentation only: these links retain the existing module routes (API_Design.md §7). --}}
@php
    $links =
        $portal === 'admin'
            ? [
                ['admin.dashboard', 'Dashboard', 'admin.dashboard', 'dashboard', 'Monitoring'],
                ['admin.bins.index', 'Bins', 'admin.bins.*', 'bins', 'Monitoring'],
                [
                    'admin.leaderboard.organizations',
                    'Org leaderboard',
                    'admin.leaderboard.*',
                    'leaderboard',
                    'Monitoring',
                ],
                ['admin.reports', 'Reports', 'admin.reports', 'reports', 'Monitoring'],
                ['admin.transactions.index', 'Transactions', 'admin.transactions.*', 'transactions', 'Deposits'],
                ['admin.sessions.review', 'Sessions in review', 'admin.sessions.*', 'review', 'Deposits'],
                ['admin.users.index', 'Users', 'admin.users.*', 'users', 'Identity'],
                ['admin.organizations.index', 'Organizations', 'admin.organizations.*', 'organizations', 'Identity'],
                ['admin.rewards.index', 'Rewards', 'admin.rewards.*', 'rewards', 'Rewards'],
                ['admin.redemptions.index', 'Redemptions', 'admin.redemptions.*', 'redemptions', 'Rewards'],
                ['admin.points-rate.show', 'Points rate', 'admin.points-rate.*', 'points', 'Rewards'],
                ['notifications.index', 'Notifications', 'notifications.*', 'notifications', 'Account'],
                ['home', 'Student view', 'home', 'profile', 'Account'],
            ]
            : [
                ['home', 'Dashboard home', 'home', 'dashboard', null],
                ['profile.show', 'My profile', 'profile.*', 'profile', null],
                ['transactions.index', 'Disposal history', 'transactions.*', 'transactions', null],
                ['leaderboard', 'Leaderboard', 'leaderboard', 'leaderboard', null],
                ['rewards.index', 'Available rewards', 'rewards.*', 'rewards', null],
                ['redemptions.index', 'My redemptions', 'redemptions.*', 'redemptions', null],
                ['notifications.index', 'Notifications', 'notifications.*', 'notifications', null],
            ];
@endphp
<nav class="nav-links" aria-label="{{ $portal === 'admin' ? 'Admin pages' : 'Student pages' }}">
    @foreach ($links as [$route, $label, $pattern, $icon, $group])
        @if ($group && ($loop->first || $group !== $links[$loop->index - 1][4]))
            <div class="nav-group">{{ $group }}</div>
        @endif
        @if ($route === 'admin.reports')
            <span class="nav-unavailable" aria-disabled="true">
                @include('layouts.partials.icon', ['name' => $icon])
                <span>Reports <small>Not available yet</small></span>
            </span>
        @else
            <a href="{{ route($route) }}" @if (request()->routeIs($pattern)) aria-current="page" @endif>
                @include('layouts.partials.icon', ['name' => $icon])
                <span>{{ $label }}</span>
            </a>
        @endif
    @endforeach
    @if ($portal === 'student' && auth()->user()->isAdmin())
        <a href="{{ route('admin.dashboard') }}">
            @include('layouts.partials.icon', ['name' => 'dashboard'])
            <span>Admin</span>
        </a>
    @endif
</nav>
