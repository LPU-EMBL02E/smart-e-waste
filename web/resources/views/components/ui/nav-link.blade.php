{{--
    One sidebar link. `active` is a route name pattern, for pages that belong to
    this link without being its route. design-system.html #nav.
--}}
@props(['route', 'active' => null, 'icon' => null, 'count' => null])

<a class="nav-link" href="{{ route($route) }}" @if (request()->routeIs($active ?? $route)) aria-current="page" @endif>
    @if ($icon)
        <x-ui.icon :name="$icon" />
    @endif
    <span>{{ $slot }}</span>
    @if ($count)
        <span class="count"><span class="sr-only">Unread: </span>{{ $count }}</span>
    @endif
</a>
