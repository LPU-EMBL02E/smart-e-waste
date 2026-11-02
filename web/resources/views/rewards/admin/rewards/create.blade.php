{{-- GET /admin/rewards/create. API_Design.md §7.4, all-pages-wireframe.html A19. --}}
@extends('layouts.admin')

@section('title', 'New reward')

@section('content')
    <x-ui.page-heading title="New reward" :back="route('admin.rewards.index')" back-label="Rewards" />

    <form class="form" method="POST" action="{{ route('admin.rewards.store') }}">
        @csrf
        @include('rewards.admin.rewards._form')
        <div class="actions">
            <x-ui.button variant="primary">Create reward</x-ui.button>
            <a class="text-link" href="{{ route('admin.rewards.index') }}">Cancel</a>
        </div>
    </form>
@endsection
