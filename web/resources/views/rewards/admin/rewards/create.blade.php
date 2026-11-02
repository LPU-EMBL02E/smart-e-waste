{{-- GET /admin/rewards/create. API_Design.md §7.4. --}}
@extends('layouts.admin')

@section('title', 'New reward')

@section('content')
    <h1>New reward</h1>

    <form class="app-form" method="POST" action="{{ route('admin.rewards.store') }}">
        @csrf
        @include('rewards.admin.rewards._form')
        <button class="button primary" type="submit">Create</button>
    </form>
@endsection
