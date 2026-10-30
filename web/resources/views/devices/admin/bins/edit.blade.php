{{-- GET /admin/bins/{device}/edit — edit a bin and its calibration. API_Design.md §7.4, §7.6. --}}
@extends('layouts.admin')

@section('title', 'Edit '.$device->name)

@section('content')
    <h1>Edit {{ $device->name }}</h1>

    <form method="POST" action="{{ route('admin.bins.update', $device) }}">
        @csrf
        @method('PUT')
        @include('devices.admin.bins._form')
        <button type="submit">Save</button>
    </form>

    <p><a href="{{ route('admin.bins.show', $device) }}">Back to the bin</a></p>
@endsection
