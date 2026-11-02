{{-- GET /admin/organizations/{organization}/edit. API_Design.md §7.4, §7.6. --}}
@extends('layouts.admin')

@section('title', 'Edit ' . $organization->name)

@section('content')
    <h1>{{ $organization->name }}</h1>

    <form class="app-form" method="POST" action="{{ route('admin.organizations.update', $organization) }}">
        @csrf
        @method('PUT')
        @include('identity.admin.organizations._form')
        <button class="button primary" type="submit">Save changes</button>
    </form>

    <p><a href="{{ route('admin.organizations.index') }}">Back to organizations</a></p>
@endsection
