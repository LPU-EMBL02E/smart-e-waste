{{-- GET /admin/bins — bin list with current fill level. API_Design.md §7.4, all-pages-wireframe.html A13. --}}
@extends('layouts.admin')

@section('title', 'Bins')

@section('content')
    <x-ui.page-heading title="Bins">
        <x-slot:actions>
            <x-ui.button variant="primary" :href="route('admin.bins.create')">Register a bin</x-ui.button>
        </x-slot:actions>
    </x-ui.page-heading>

    @if ($devices->isEmpty())
        <x-ui.empty title="No bins registered yet" />
    @else
        <section class="section">
            <x-ui.table label="Bins">
                <x-slot:head>
                    <th scope="col">Name</th>
                    <th scope="col">Device code</th>
                    <th scope="col">Location</th>
                    <th scope="col">Status</th>
                    <th scope="col">Fill level</th>
                    <th scope="col">Panel</th>
                    <th scope="col">Fault</th>
                    <th scope="col">Last seen ({{ config('ewaste.display_timezone') }})</th>
                    <th scope="col">Firmware</th>
                </x-slot:head>

                @foreach ($devices as $device)
                    @php($reading = $device->latestTelemetry)
                    <tr>
                        <td><a href="{{ route('admin.bins.show', $device) }}">{{ $device->name }}</a></td>
                        <td>{{ $device->device_code }}</td>
                        <td>{{ $device->location ?? '—' }}</td>
                        <td><x-ui.pill :status="$device->status" /></td>
                        <td><x-ui.meter :percent="$reading?->fill_percent" :status="$reading?->status" /></td>
                        <td>{{ $reading ? ($reading->panel_open ? 'Open' : 'Closed') : '—' }}</td>
                        <td>
                            @if ($reading?->fault_code)
                                <x-ui.pill tone="danger">@faultlabel($reading->fault_code->value)</x-ui.pill>
                            @else
                                {{ $reading ? 'None' : '—' }}
                            @endif
                        </td>
                        <td>@datetime($device->last_seen_at, 'Never')</td>
                        <td>{{ $device->firmware_version ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-ui.table>

            {{ $devices->links() }}
        </section>
    @endif
@endsection
