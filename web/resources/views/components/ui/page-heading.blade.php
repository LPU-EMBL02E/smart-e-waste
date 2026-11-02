{{--
    A page's one h1, with an optional back link, description and actions.
    web/docs/DESIGN.md §4, design-system.html.
--}}
@props(['title', 'description' => null, 'back' => null, 'backLabel' => null, 'actions' => null])

<header {{ $attributes->class('heading') }}>
    <div>
        @if ($back)
            <a class="back-link" href="{{ $back }}">← {{ $backLabel }}</a>
        @endif
        <h1>{{ $title }}</h1>
        @if ($description)
            <p>{{ $description }}</p>
        @endif
    </div>
    @if ($actions)
        <div class="actions">{{ $actions }}</div>
    @endif
</header>
