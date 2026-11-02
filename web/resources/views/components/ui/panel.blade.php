{{-- A bordered group of content, with an optional heading. design-system.html #panels. --}}
@props(['title' => null])

<section {{ $attributes->class('panel') }}>
    @if ($title)
        <h2 class="panel-title">{{ $title }}</h2>
    @endif
    {{ $slot }}
</section>
