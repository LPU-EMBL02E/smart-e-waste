{{--
    The document every page shares. resources/views/README.md.

    System fonts only, so nothing here makes a third-party request
    (web/docs/DESIGN.md §10). A page adds its own script with @push('scripts').

    data-theme is set only when the visitor chose light or dark; without it the
    page follows the device (API_Design.md P52, DESIGN.md §3).
--}}
<!DOCTYPE html>
<html lang="en"@if ($theme !== \App\Modules\Identity\Support\Theme::System) data-theme="{{ $theme->value }}"@endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="{{ $theme === \App\Modules\Identity\Support\Theme::System ? 'light dark' : $theme->value }}">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    @vite('resources/css/app.css')
    @stack('scripts')
</head>
<body>
    <a class="skip" href="#main">Skip to content</a>

    @yield('body')
</body>
</html>
