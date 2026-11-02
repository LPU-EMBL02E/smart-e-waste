{{--
    GET /admin/bins/{device} — one bin, its fill history, service-panel state and
    faults. API_Design.md §7.4, all-pages-wireframe.html A15.
--}}
@extends('layouts.admin')

@section('title', $device->name)

@section('content')
    <x-ui.page-heading :title="$device->name" :back="route('admin.bins.index')" back-label="Bins">
        <x-slot:actions>
            <x-ui.button :href="route('admin.bins.edit', $device)">Edit bin</x-ui.button>
        </x-slot:actions>
    </x-ui.page-heading>

    <div class="split">
        <x-ui.panel title="Now">
            <dl class="details-list">
                <dt>Fill level</dt>
                <dd><x-ui.meter :percent="$latest?->fill_percent" :status="$latest?->status" /></dd>
                <dt>Service panel</dt>
                <dd>{{ $latest ? ($latest->panel_open ? 'Open' : 'Closed') : '—' }}</dd>
                <dt>Fault</dt>
                <dd>
                    @if ($latest?->fault_code)
                        <x-ui.pill tone="danger">@faultlabel($latest->fault_code->value)</x-ui.pill>
                        <code>{{ $latest->fault_code->value }}</code>
                    @else
                        {{ $latest ? 'None' : '—' }}
                    @endif
                </dd>
                <dt>Last seen</dt>
                <dd>@datetimeseconds($device->last_seen_at, 'Never')</dd>
                <dt>Firmware</dt>
                <dd>{{ $device->firmware_version ?? '—' }}</dd>
            </dl>
        </x-ui.panel>

        <x-ui.panel title="Setup">
            <dl class="details-list">
                <dt>Device code</dt>
                <dd>{{ $device->device_code }}</dd>
                <dt>Location</dt>
                <dd>{{ $device->location ?? '—' }}</dd>
                <dt>Status</dt>
                <dd><x-ui.pill :status="$device->status" /></dd>
                <dt>Empty distance</dt>
                <dd>{{ $device->empty_distance_mm }} mm</dd>
                <dt>Fill threshold</dt>
                <dd>@percent($device->fill_threshold_percent)</dd>
                <dt>Calibration</dt>
                <dd>
                    @if ($device->isCalibrated())
                        offset {{ $device->weight_offset }}, scale {{ $device->weight_scale_factor }}<br>
                        <span class="small muted">calibrated @date($device->calibrated_at)</span>
                    @else
                        Not calibrated
                    @endif
                </dd>
            </dl>
        </x-ui.panel>
    </div>

    <section class="section">
        <h2>Fill history</h2>

        @if ($readings->isEmpty())
            <x-ui.empty title="No readings yet" />
        @else
            <x-ui.table label="Fill history">
                <x-slot:head>
                    <th scope="col">Time ({{ config('ewaste.display_timezone') }})</th>
                    <th scope="col" class="numeric">Distance</th>
                    <th scope="col" class="numeric">Fill</th>
                    <th scope="col">Status</th>
                    <th scope="col">Panel</th>
                    <th scope="col">Fault</th>
                </x-slot:head>

                @foreach ($readings as $reading)
                    <tr>
                        <td>@datetimeseconds($reading->created_at)</td>
                        <td class="numeric">{{ $reading->distance_mm }} mm</td>
                        <td class="numeric">@percent($reading->fill_percent)</td>
                        <td><x-ui.pill :status="$reading->status" /></td>
                        <td>{{ $reading->panel_open ? 'Open' : 'Closed' }}</td>
                        <td>
                            @if ($reading->fault_code)
                                <code>{{ $reading->fault_code->value }}</code>
                            @else
                                None
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            {{ $readings->links() }}
        @endif
    </section>

    <x-ui.panel title="Device secret" class="panel-narrow">
        <div class="section">
            <p>
                Issue a new secret if the old one was lost or exposed. The old secret stops working at once; the bin
                can't take deposits until you enter the new one in its setup portal.
            </p>
            <form method="POST" action="{{ route('admin.bins.secret', $device) }}">
                @csrf
                <x-ui.button variant="danger">Issue a new secret</x-ui.button>
            </form>
        </div>
    </x-ui.panel>
@endsection
