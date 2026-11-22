{{-- GET /admin/bins — bin list with current fill level. API_Design.md §7.4. --}}
@extends('layouts.admin')

@section('title', 'Bins')

@section('content')
    <h1>Bins</h1>

    <p><a href="{{ route('admin.bins.create') }}">Register a bin</a></p>

    <div style="overflow-x: auto">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Code</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Fill</th>
                    <th>Panel</th>
                    <th>Fault</th>
                    <th>Last seen</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($devices as $device)
                    <tr>
                        <td><a href="{{ route('admin.bins.show', $device) }}">{{ $device->name }}</a></td>
                        <td>{{ $device->device_code }}</td>
                        <td>{{ $device->location }}</td>
                        <td>{{ $device->status }}</td>
                        @if ($device->latestTelemetry)
                            <td>{{ $device->latestTelemetry->fill_percent }}% ({{ $device->latestTelemetry->status }})</td>
                            <td>{{ $device->latestTelemetry->panel_open ? 'Open' : 'Closed' }}</td>
                            <td>{{ $device->latestTelemetry->fault_code?->value ?? 'None' }}</td>
                        @else
                            <td colspan="3">No reading yet</td>
                        @endif
                        <td>{{ $device->last_seen_at?->timezone(config('ewaste.display_timezone'))->format('d M Y, g:ia') ?? 'Never' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">No bins registered yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $devices->links() }}
@endsection
