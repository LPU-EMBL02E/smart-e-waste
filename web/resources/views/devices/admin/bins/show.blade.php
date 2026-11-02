{{--
    GET /admin/bins/{device} — one bin, its fill history, service-panel state and
    faults. API_Design.md §7.4.
--}}
@extends('layouts.admin')

@section('title', $device->name)

@section('content')
    <h1>{{ $device->name }}</h1>

    <dl class="details-list">
        <dt>Device code</dt>
        <dd>{{ $device->device_code }}</dd>
        <dt>Location</dt>
        <dd>{{ $device->location ?? '—' }}</dd>
        <dt>Status</dt>
        <dd>{{ $device->status }}</dd>
        <dt>Firmware</dt>
        <dd>{{ $device->firmware_version ?? '—' }}</dd>
        <dt>Last seen</dt>
        <dd>{{ $device->last_seen_at?->timezone(config('ewaste.display_timezone'))->format('d M Y, g:ia') ?? 'Never' }}</dd>
        <dt>Empty distance</dt>
        <dd>{{ $device->empty_distance_mm }} mm, full at {{ $device->fill_threshold_percent }}%</dd>
        <dt>Calibration</dt>
        <dd>
            @if ($device->isCalibrated())
                offset {{ $device->weight_offset }}, scale {{ $device->weight_scale_factor }},
                entered {{ $device->calibrated_at?->timezone(config('ewaste.display_timezone'))->format('d M Y, g:ia') }}
            @else
                Not calibrated
            @endif
        </dd>
        <dt>Now</dt>
        <dd>
            @if ($latest)
                {{ $latest->fill_percent }}% ({{ $latest->status }}),
                panel {{ $latest->panel_open ? 'open' : 'closed' }},
                fault: {{ $latest->fault_code?->value ?? 'none' }}
            @else
                No reading yet
            @endif
        </dd>
    </dl>

    <p><a href="{{ route('admin.bins.edit', $device) }}">Edit</a></p>

    <form class="app-form" method="POST" action="{{ route('admin.bins.secret', $device) }}">
        @csrf
        <button class="button primary" type="submit">Issue a new secret</button>
        The bin stops working until the new secret is entered in its setup portal.
    </form>

    <h2>Fill history</h2>

    <div class="table-region" role="region" tabindex="0" aria-label="Records">
        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col">Time</th>
                    <th scope="col" class="numeric">Distance</th>
                    <th scope="col" class="numeric">Fill</th>
                    <th scope="col">Panel</th>
                    <th scope="col">Fault</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($readings as $reading)
                    <tr>
                        <td>{{ $reading->created_at->timezone(config('ewaste.display_timezone'))->format('d M Y, g:ia') }}
                        </td>
                        <td class="numeric">{{ $reading->distance_mm }} mm</td>
                        <td class="numeric">{{ $reading->fill_percent }}% ({{ $reading->status }})</td>
                        <td>{{ $reading->panel_open ? 'Open' : 'Closed' }}</td>
                        <td>{{ $reading->fault_code?->value ?? 'None' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">No readings yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $readings->links('pagination.portal') }}
@endsection
