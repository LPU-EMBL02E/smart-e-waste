{{--
    The document every page shares. resources/views/README.md.

    System fonts only, so nothing here makes a third-party request
    (web/docs/DESIGN.md §10). A page adds its own script with @push('scripts').
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name') }}</title>
    @vite('resources/css/app.css')
    @stack('scripts')
</head>
<body>
    <a class="skip" href="#main">Skip to content</a>

    @yield('body')
</body>
</html>
