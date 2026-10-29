{{--
    Admin shell. resources/views/README.md.

    Bare on purpose: logic first, the DESIGN.md pass comes later
    (web/docs/TRACK-A-ACCOUNTS.md, step A0).
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name') }} admin</title>
</head>
<body>
    <nav>
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <a href="{{ route('admin.bins.index') }}">Bins</a>
        <a href="{{ route('admin.transactions.index') }}">Transactions</a>
        <a href="{{ route('admin.sessions.review') }}">Sessions to review</a>
        <a href="{{ route('admin.points-rate.show') }}">Points rate</a>
        <a href="{{ route('admin.rewards.index') }}">Rewards</a>
        <a href="{{ route('admin.redemptions.index') }}">Redemptions</a>
        <a href="{{ route('admin.users.index') }}">Users</a>
        <a href="{{ route('admin.organizations.index') }}">Organizations</a>
        <a href="{{ route('admin.leaderboard.organizations') }}">Organization leaderboard</a>
        <a href="{{ route('admin.reports') }}">Reports</a>
        <a href="{{ route('notifications.index') }}">Notifications</a>
        @include('layouts.partials.logout')
    </nav>

    <main>
        @include('layouts.partials.messages')

        @yield('content')
    </main>
</body>
</html>
