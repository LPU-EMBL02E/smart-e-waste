{{--
    A bin's fill level: the meter, the percentage and the server's status. The
    server owns the status; this only shows it. A bin with no reading says so,
    never 0%. web/docs/DESIGN.md §7.
--}}
@props(['percent' => null, 'status' => null])

@if ($percent === null)
    <span {{ $attributes->class('reading') }}>No reading</span>
@else
    <span {{ $attributes->class('reading') }}>
        <span class="meter" aria-hidden="true"><i style="width: {{ min(100, max(0, (float) $percent)) }}%"></i></span>
        <span>@percent($percent)</span>
        @if ($status)
            <x-ui.pill :status="$status" />
        @endif
    </span>
@endif
