{{-- Web error presentation only; device routes retain their JSON errors (API_Design.md §3.5). --}}
<!DOCTYPE html>
<html lang="en">

<head>
    @include('layouts.partials.head')
</head>

<body class="ew-app">
    <a class="skip-link" href="#main-content">Skip to content</a>
    <main class="error-page page-content" id="main-content" tabindex="-1">
        <a class="brand" href="{{ url('/') }}"><span class="brand-mark" aria-hidden="true">EW</span>Smart E-Waste</a>
        <p class="refresh-note">Error {{ trim($__env->yieldContent('code')) }}</p>
        <h1>{{ trim($__env->yieldContent('title')) }}</h1>
        <p>{{ trim($__env->yieldContent('message')) }}</p>
        <div class="quick-actions"><a class="button primary" href="{{ url('/') }}">Return to portal</a></div>
    </main>
</body>

</html>
