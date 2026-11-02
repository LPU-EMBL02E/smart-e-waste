{{-- A flat tile holding one documented total. design-system.html #panels. --}}
@props(['label', 'foot' => null])

<div {{ $attributes->class('tile') }}>
    <div class="tile-label">{{ $label }}</div>
    <div class="tile-value">{{ $slot }}</div>
    @if ($foot)
        <div class="tile-foot">{{ $foot }}</div>
    @endif
</div>
