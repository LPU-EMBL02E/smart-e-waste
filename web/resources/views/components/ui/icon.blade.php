{{--
    A decorative inline icon, hidden from screen readers: pair it with text.
    web/docs/DESIGN.md §6, design-system.html #icons.
--}}
@props(['name'])

<span {{ $attributes->class('symbol') }}>
    <svg viewBox="0 0 24 24" aria-hidden="true">
        @switch($name)
            @case('home')
                <rect x="3" y="3" width="7" height="7" />
                <rect x="14" y="3" width="7" height="7" />
                <rect x="3" y="14" width="7" height="7" />
                <rect x="14" y="14" width="7" height="7" />
                @break
            @case('profile')
                <circle cx="12" cy="8" r="4" />
                <path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8" />
                @break
            @case('rewards')
                <rect x="3" y="8" width="18" height="13" />
                <path d="M12 8v13M3 12h18M12 8c-2-4-6-4-6-1.5S10 8 12 8zm0 0c2-4 6-4 6-1.5S14 8 12 8z" />
                @break
            @case('notifications')
                <path d="M6 9a6 6 0 0 1 12 0c0 6 2 7 2 7H4s2-1 2-7zM10 20a2 2 0 0 0 4 0" />
                @break
            @case('logout')
                <path d="M9 4H4v16h5M16 8l4 4-4 4M20 12H9" />
                @break
            @case('history')
                <path d="M6 3h12v18H6z M9 8h6 M9 12h6 M9 16h4" />
                @break
            @case('leaderboard')
                <path d="M4 21V11h5v10 M9 21V4h6v17 M15 21v-7h5v7" />
                @break
            @case('redemptions')
                <path d="M3 7h16l-4-4 M21 17H5l4 4 M19 7l-4 4 M5 17l4-4" />
                @break
            @case('admin')
                <path d="M3 21h18 M5 21V8h14v13 M3 8l9-5 9 5 M9 11v7 M15 11v7" />
                @break
        @endswitch
    </svg>
</span>
