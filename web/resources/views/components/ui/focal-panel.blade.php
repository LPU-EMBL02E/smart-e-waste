{{--
    The page's one lavender focal panel, with a static hard shadow.
    design-system.html #panels. Give the unit separately so it can sit smaller.
--}}
@props(['label', 'unit' => null, 'foot' => null])

<div {{ $attributes->class(['tile', 'points-panel']) }}>
    <div class="tile-label">{{ $label }}</div>
    <div class="tile-value">
        {{ $slot }}
        @if ($unit)
            <span class="points-unit">{{ $unit }}</span>
        @endif
    </div>
    @if ($foot)
        <div class="tile-foot">{{ $foot }}</div>
    @endif
</div>
