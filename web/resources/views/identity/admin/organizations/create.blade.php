{{-- GET /admin/organizations/create. API_Design.md §7.4, §7.6; all-pages-wireframe.html A8. --}}
@extends('layouts.admin')

@section('title', 'New organization')

@section('content')
    <x-ui.page-heading title="New organization" :back="route('admin.organizations.index')" back-label="Organizations" />

    <form class="form" method="POST" action="{{ route('admin.organizations.store') }}">
        @csrf
        @include('identity.admin.organizations._form')
        <div class="actions">
            <x-ui.button variant="primary">Create organization</x-ui.button>
            <a class="text-link" href="{{ route('admin.organizations.index') }}">Cancel</a>
        </div>
    </form>
@endsection
