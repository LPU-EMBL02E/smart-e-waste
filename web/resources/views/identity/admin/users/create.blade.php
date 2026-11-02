{{-- GET /admin/users/create. API_Design.md §7.4, §7.6; all-pages-wireframe.html A5. --}}
@extends('layouts.admin')

@section('title', 'New user')

@section('content')
    <x-ui.page-heading title="New user" :back="route('admin.users.index')" back-label="Users" />

    <form class="form" method="POST" action="{{ route('admin.users.store') }}">
        @csrf
        @include('identity.admin.users._form')
        <p class="hint">We'll email the user a link to set their password.</p>
        <div class="actions">
            <x-ui.button variant="primary">Create user</x-ui.button>
            <a class="text-link" href="{{ route('admin.users.index') }}">Cancel</a>
        </div>
    </form>
@endsection
