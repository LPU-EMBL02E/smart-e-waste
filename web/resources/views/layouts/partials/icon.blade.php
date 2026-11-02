{{-- Reuse the student HTML wireframe's outline icons; DESIGN.md §6. --}}
@php
    $paths = [
        'dashboard' => 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
        'profile' => 'M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0 M4 21v-2a8 8 0 0 1 16 0v2',
        'points' => 'M20 12a8 8 0 1 1-16 0 8 8 0 0 1 16 0 M12 7v10 M9 10h4a2 2 0 0 1 0 4H9',
        'transactions' => 'M6 3h12v18H6z M9 8h6 M9 12h6 M9 16h4',
        'organizations' => 'M3 21h18 M5 21V8h14v13 M3 8l9-5 9 5 M9 11v7 M15 11v7',
        'leaderboard' => 'M4 21V11h5v10 M9 21V4h6v17 M15 21v-7h5v7',
        'rewards' => 'M3 8h18v5H3z M5 13v8h14v-8 M12 8v13 M12 8C3 8 6 1 9 4l3 4c9 0 6-7 3-4z',
        'redemptions' => 'M3 7h16l-4-4 M21 17H5l4 4 M19 7l-4 4 M5 17l4-4',
        'notifications' =>
            'M13 4V2.5a2 2 0 0 1 4 0V4 M15 4c-6 0-9.2 4.7-9.2 10.5V21c0 1.7-1.5 3.5-3.1 4.5-.8.6-.4 1.8.6 1.8h23.4c1 0 1.4-1.2.6-1.8-1.6-1-3.1-2.8-3.1-4.5v-6.5C24.2 8.7 21 4 15 4Z M11 28c.3 2.2 1.7 3.1 4 3.1s3.7-.9 4-3.1',
        'logout' => 'M9 3H4v18h5 M9 12h12 M17 8l4 4-4 4',
        'bins' => 'M3 6h18 M9 6V3h6v3 M5 6l1 15h12l1-15 M10 10v7 M14 10v7',
        'review' => 'M8 4H5v17h14V4h-3 M8 2h8v4H8z M8 13l3 3 5-6',
        'users' =>
            'M16 21v-2a5 5 0 0 0-10 0v2 M15 4a4 4 0 1 1 0 8 M20 21v-2a5 5 0 0 0-3-4 M15 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
        'reports' => 'M6 3h9l4 4v14H6z M14 3v5h5 M9 17v-3 M12 17v-5 M15 17v-7',
        'qr' =>
            'M3 3h6v6H3z M15 3h6v6h-6z M3 15h6v6H3z M15 15h2v2h-2z M20 15v3h-3v3h4 M12 3v5 M3 12h5 M12 12v9 M15 12h6',
    ];
@endphp
<svg class="portal-icon" viewBox="{{ $name === 'notifications' ? '0 0 30 32' : '0 0 24 24' }}" width="24" height="24"
    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
    focusable="false">
    <path d="{{ $paths[$name] }}" />
</svg>
