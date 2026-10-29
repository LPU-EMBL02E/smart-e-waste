{{--
    Signed-in student shell. resources/views/README.md.

    Bare on purpose: logic first, the DESIGN.md pass comes later
    (web/docs/TRACK-A-ACCOUNTS.md, step A0).
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name') }}</title>
</head>
<body>
    <nav>
        <a href="{{ route('home') }}">Home</a>
        <a href="{{ route('transactions.index') }}">Transactions</a>
        <a href="{{ route('rewards.index') }}">Rewards</a>
        <a href="{{ route('redemptions.index') }}">My redemptions</a>
        <a href="{{ route('leaderboard') }}">Leaderboard</a>
        <a href="{{ route('notifications.index') }}">Notifications</a>
        <a href="{{ route('profile.show') }}">Profile</a>
        @include('layouts.partials.logout')
    </nav>

    <main>
        @include('layouts.partials.messages')

        @yield('content')
    </main>
</body>
</html>
