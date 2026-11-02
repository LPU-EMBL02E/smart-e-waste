{{-- Shared authenticated shell. DESIGN.md §§5, 9–10; existing route guards own access. --}}
<!DOCTYPE html>
<html lang="en">

<head>
    @include('layouts.partials.head')
</head>

<body class="ew-app {{ $portal }}-portal">
    <a class="skip-link" href="#main-content">Skip to content</a>
    <div class="portal-shell">
        <aside class="sidebar">
            <details class="portal-navigation" open>
                <summary>{{ $portal === 'admin' ? 'Admin navigation' : 'Student navigation' }}</summary>
                <a class="brand" href="{{ route($portal === 'admin' ? 'admin.dashboard' : 'home') }}">
                    <span class="brand-mark" aria-hidden="true">EW</span>
                    <span>Smart
                        E-Waste<small>{{ $portal === 'admin' ? 'Admin portal' : 'Student portal' }}</small></span>
                </a>
                @include('layouts.partials.navigation')
                <div class="sidebar-footer">@include('layouts.partials.logout')</div>
            </details>
        </aside>
        <div class="portal-main">
            <header class="portal-header">
                <span class="portal-title">{{ trim($__env->yieldContent('title')) }}</span>
                <div class="portal-header-actions">
                    <a class="button icon-button" href="{{ route('notifications.index') }}" aria-label="Notifications"
                        title="Notifications">
                        @include('layouts.partials.icon', ['name' => 'notifications'])
                    </a>
                    <div class="account-avatar" aria-hidden="true">
                        {{ mb_strtoupper(mb_substr(auth()->user()->first_name, 0, 1) . mb_substr(auth()->user()->last_name, 0, 1)) }}
                    </div>
                    <div class="account-identity">
                        <strong>{{ auth()->user()->fullName() }}</strong>
                        <span>{{ auth()->user()->isAdmin() ? 'Administrator' : 'Student account' }}</span>
                    </div>
                </div>
            </header>
            <main class="page-content" id="main-content" tabindex="-1">
                @include('layouts.partials.messages')
                @yield('content')
            </main>
            <footer class="portal-footer">Smart E-Waste · Times shown in {{ config('ewaste.display_timezone') }}.
            </footer>
        </div>
    </div>
</body>

</html>
