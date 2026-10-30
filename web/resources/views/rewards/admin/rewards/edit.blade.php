{{-- GET /admin/rewards/{reward}/edit. API_Design.md §7.4. A new cost never changes past redemptions. --}}
@extends('layouts.admin')

@section('title', 'Edit '.$reward->name)

@section('content')
    <h1>Edit {{ $reward->name }}</h1>

    <form method="POST" action="{{ route('admin.rewards.update', $reward) }}">
        @csrf
        @method('PUT')
        @include('rewards.admin.rewards._form')
        <button type="submit">Save</button>
    </form>

    <p><a href="{{ route('admin.rewards.index') }}">Back to rewards</a></p>
@endsection
