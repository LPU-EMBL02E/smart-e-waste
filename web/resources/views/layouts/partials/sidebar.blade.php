{{--
    The sidebar: brand, navigation and account. design-system.html #nav.

    Expects $portal (student or admin) from the shell, and $unreadNotifications
    from the view composer in ModuleServiceProvider. The student list stays open
    on a phone so its links are visible; the longer admin list starts closed
    (web/docs/DESIGN.md §5). Both are always open on a desktop.
--}}
@php($isAdminPortal = $portal === 'admin')

<aside class="sidebar">
    <div class="sidebar-brand">
        <a class="brand" href="{{ route($isAdminPortal ? 'admin.dashboard' : 'home') }}">
            <span class="brand-mark" aria-hidden="true">EW</span>
            <span class="brand-copy">
                <strong>{{ config('app.name') }}</strong>
                <small>{{ $isAdminPortal ? 'Admin portal' : 'Student portal' }}</small>
            </span>
        </a>
    </div>

    <details class="sidebar-menu" @if (! $isAdminPortal) open @endif>
        <summary class="nav-summary">Menu</summary>

        @include($isAdminPortal ? 'layouts.partials.nav-admin' : 'layouts.partials.nav-student')

        <div class="sidebar-foot">
            <p class="sidebar-user">{{ auth()->user()->fullName() }}</p>
            @include('layouts.partials.logout')
        </div>
    </details>
</aside>
