{{-- GET /admin/bins/create — register a bin. API_Design.md §7.4, §7.6. --}}
@extends('layouts.admin')

@section('title', 'Register a bin')

@section('content')
    <h1>Register a bin</h1>

    <form method="POST" action="{{ route('admin.bins.store') }}">
        @csrf
        @include('devices.admin.bins._form')
        <button type="submit">Register and issue a secret</button>
    </form>
@endsection
