{{--
    One row of the dashboard's bin table. The page draws it from the summary; the
    script clones the empty copy in a <template> and fills the data-bin elements,
    so there is only this one piece of markup.

    Expects $bin: a row of ReportingQueries::dashboardSummary()['bins'], or null
    for the template. A bin with no reading says so, never 0% (DESIGN.md §7).
--}}
@php
    $hasReading = ($bin['fill_percent'] ?? null) !== null;
    $status = $bin['status'] ?? null;
    $fault = $bin['fault_code'] ?? null;
@endphp

<tr>
    <td><a data-bin="link" href="{{ $bin ? route('admin.bins.show', $bin['id']) : '' }}">{{ $bin['name'] ?? '' }}</a></td>
    <td>
        <span class="reading" data-bin="reading" @unless ($hasReading) hidden @endunless>
            <span class="meter" aria-hidden="true"><i data-bin="meter" style="width: {{ min(100, max(0, $bin['fill_percent'] ?? 0)) }}%"></i></span>
            <span data-bin="percent">@if ($hasReading) @percent($bin['fill_percent']) @endif</span>
        </span>
        <span data-bin="no-reading" @if ($hasReading) hidden @endif>No reading</span>
    </td>
    <td>
        <x-ui.pill :$status data-bin="status" :hidden="$status === null" />
        <span data-bin="no-status" @if ($status) hidden @endif>—</span>
    </td>
    <td>
        <x-ui.pill tone="warn" data-bin="panel-open" :hidden="! ($bin['panel_open'] ?? false)">Open</x-ui.pill>
        <span data-bin="panel-closed" @if (($bin['panel_open'] ?? null) !== false) hidden @endif>Closed</span>
        <span data-bin="no-panel" @if ($hasReading) hidden @endif>—</span>
    </td>
    <td>
        <span data-bin="fault" @unless ($fault) hidden @endunless>
            <x-ui.pill tone="danger" data-bin="fault-label">@if ($fault) @faultlabel($fault) @endif</x-ui.pill>
            <code data-bin="fault-code">{{ $fault }}</code>
        </span>
        <span data-bin="no-fault" @if ($fault) hidden @endif>{{ $hasReading ? 'None' : '—' }}</span>
    </td>
    <td data-bin="last-seen">@datetime(isset($bin['last_seen_at']) ? \Illuminate\Support\Carbon::parse($bin['last_seen_at']) : null, 'Never')</td>
</tr>
