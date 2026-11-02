{{--
    A data table inside its own keyboard-scrollable region, so a wide table never
    scrolls the page. web/docs/DESIGN.md §§5–6, design-system.html #tables.

    `label` names both the region and the table. Put the <th> cells in `head`
    and the <tr> rows in the slot.
--}}
@props(['label', 'head'])

<div {{ $attributes->class('table-wrap') }} role="region" tabindex="0" aria-label="{{ $label }}">
    <table class="data-table">
        <caption class="sr-only">{{ $label }}</caption>
        <thead>
            <tr>{{ $head }}</tr>
        </thead>
        <tbody>{{ $slot }}</tbody>
    </table>
</div>
