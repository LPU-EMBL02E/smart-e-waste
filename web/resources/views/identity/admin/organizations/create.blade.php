{{-- GET /admin/organizations/create. API_Design.md §7.4, §7.6. --}}
@extends('layouts.admin')

@section('title', 'New organization')

@section('content')
    <h1>New organization</h1>

    <form class="app-form" method="POST" action="{{ route('admin.organizations.store') }}">
        @csrf
        @include('identity.admin.organizations._form')
        <button class="button primary" type="submit">Create organization</button>
    </form>

    <p><a href="{{ route('admin.organizations.index') }}">Back to organizations</a></p>
@endsection
