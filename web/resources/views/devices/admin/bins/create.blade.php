{{-- GET /admin/bins/create — register a bin. API_Design.md §7.4, §7.6; all-pages-wireframe.html A14. --}}
@extends('layouts.admin')

@section('title', 'Register a bin')

@section('content')
    <x-ui.page-heading title="Register a bin" :back="route('admin.bins.index')" back-label="Bins" />

    <form class="form" method="POST" action="{{ route('admin.bins.store') }}">
        @csrf
        @include('devices.admin.bins._form')
        <p class="hint">Registering creates the bin's secret. You'll see it once on the next page.</p>
        <div class="actions">
            <x-ui.button variant="primary">Register bin</x-ui.button>
            <a class="text-link" href="{{ route('admin.bins.index') }}">Cancel</a>
        </div>
    </form>
@endsection
