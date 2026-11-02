{{-- An empty list: a short heading and context. web/docs/DESIGN.md §8. --}}
@props(['title'])

<div {{ $attributes->class('empty') }}>
    <h2>{{ $title }}</h2>
    @if ($slot->isNotEmpty())
        <p>{{ $slot }}</p>
    @endif
</div>
