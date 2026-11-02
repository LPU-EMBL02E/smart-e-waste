{{--
    GET /admin/rewards/{reward}/edit. API_Design.md §7.4, all-pages-wireframe.html A20.
    A new cost never changes past redemptions.
--}}
@extends('layouts.admin')

@section('title', 'Edit '.$reward->name)

@section('content')
    <x-ui.page-heading :title="'Edit '.$reward->name" :back="route('admin.rewards.index')" back-label="Rewards" />

    <form class="form" method="POST" action="{{ route('admin.rewards.update', $reward) }}">
        @csrf
        @method('PUT')
        @include('rewards.admin.rewards._form')
        <div class="actions">
            <x-ui.button variant="primary">Save changes</x-ui.button>
            <a class="text-link" href="{{ route('admin.rewards.index') }}">Cancel</a>
        </div>
    </form>
@endsection
