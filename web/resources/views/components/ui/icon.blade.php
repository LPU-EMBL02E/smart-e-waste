{{--
    A decorative inline icon, hidden from screen readers: pair it with text.
    web/docs/DESIGN.md §6, design-system.html #icons.
--}}
@props(['name'])

<span {{ $attributes->class('symbol') }}>
    <svg viewBox="0 0 24 24" aria-hidden="true">
        @switch($name)
            @case('home')
                <path d="M3 11l9-8 9 8 M5 9.5V21h14V9.5 M10 21v-6h4v6" />
                @break
            @case('dashboard')
                <rect x="3" y="3" width="7" height="7" />
                <rect x="14" y="3" width="7" height="7" />
                <rect x="3" y="14" width="7" height="7" />
                <rect x="14" y="14" width="7" height="7" />
                @break
            @case('bins')
                {{-- A collection bin: deposit slot and fill line. --}}
                <rect x="5" y="3" width="14" height="18" />
                <path d="M9 7h6 M5 14h14" />
                @break
            @case('session-review')
                <circle cx="10.5" cy="10.5" r="6.5" />
                <path d="M15.5 15.5L21 21 M7.5 10.5l2 2 3.5-3.5" />
                @break
            @case('transactions')
                {{-- An item going into the bin. --}}
                <path d="M12 3v11 M8 10l4 4 4-4 M4 15v6h16v-6" />
                @break
            @case('points-rate')
                {{-- A balance: grams in, points out. --}}
                <path d="M12 3v18 M7 21h10 M4 7h16 M4 7l-2.5 6a2.5 2.5 0 0 0 5 0z M20 7l-2.5 6a2.5 2.5 0 0 0 5 0z" />
                @break
            @case('users')
                <circle cx="9" cy="8" r="3.5" />
                <path d="M2 20c0-3.9 3.1-7 7-7s7 3.1 7 7 M16 4.5a3.5 3.5 0 0 1 0 7 M18 13.3c2.4 .9 4 3.3 4 6.7" />
                @break
            @case('organizations')
                <rect x="4" y="3" width="11" height="18" />
                <path d="M15 9h5v12h-5 M8 7h3 M8 11h3 M8 15h3 M2 21h20" />
                @break
            @case('reports')
                <path d="M6 3h9l3 3v15H6z M9 17v-3 M12 17v-6 M15 17v-4" />
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
