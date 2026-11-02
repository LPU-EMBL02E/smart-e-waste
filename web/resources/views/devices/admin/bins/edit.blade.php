{{-- GET /admin/bins/{device}/edit — edit a bin and its calibration. API_Design.md §7.4, §7.6; all-pages-wireframe.html A16. --}}
@extends('layouts.admin')

@section('title', 'Edit '.$device->name)

@section('content')
    <x-ui.page-heading :title="'Edit '.$device->name" :back="route('admin.bins.show', $device)" :back-label="$device->name" />

    <form class="form" method="POST" action="{{ route('admin.bins.update', $device) }}">
        @csrf
        @method('PUT')
        @include('devices.admin.bins._form')
        <div class="actions">
            <x-ui.button variant="primary">Save changes</x-ui.button>
            <a class="text-link" href="{{ route('admin.bins.show', $device) }}">Cancel</a>
        </div>
    </form>
@endsection
