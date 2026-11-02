{{-- GET /admin/organizations/{organization}/edit. API_Design.md §7.4, §7.6; all-pages-wireframe.html A9. --}}
@extends('layouts.admin')

@section('title', 'Edit '.$organization->name)

@section('content')
    <x-ui.page-heading :title="$organization->name" :back="route('admin.organizations.index')" back-label="Organizations" />

    <form class="form" method="POST" action="{{ route('admin.organizations.update', $organization) }}">
        @csrf
        @method('PUT')
        @include('identity.admin.organizations._form')
        <div class="actions">
            <x-ui.button variant="primary">Save changes</x-ui.button>
            <a class="text-link" href="{{ route('admin.organizations.index') }}">Cancel</a>
        </div>
    </form>
@endsection
