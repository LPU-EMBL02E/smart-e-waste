{{-- GET /admin/users/create. API_Design.md §7.4, §7.6. --}}
@extends('layouts.admin')

@section('title', 'New user')

@section('content')
    <h1>New user</h1>

    <form class="app-form" method="POST" action="{{ route('admin.users.store') }}">
        @csrf
        @include('identity.admin.users._form')
        <p>We'll email the user a link to set their password.</p>
        <button class="button primary" type="submit">Create user</button>
    </form>

    <p><a href="{{ route('admin.users.index') }}">Back to users</a></p>
@endsection
