{{--
    Guest shell: login and password pages. resources/views/README.md.

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
        <a href="{{ route('login') }}">Sign in</a>
        <a href="{{ route('password.request') }}">Forgot password</a>
    </nav>

    <main>
        @include('layouts.partials.messages')

        @yield('content')
    </main>
</body>
</html>
