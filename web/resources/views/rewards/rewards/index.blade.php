{{-- GET /rewards — the catalogue of active rewards. API_Design.md §7.3. --}}
@extends('layouts.app')

@section('title', 'Rewards')

@section('content')
    <h1>Rewards</h1>

    @forelse ($rewards as $reward)
        <article>
            <h2><a href="{{ route('rewards.show', $reward) }}">{{ $reward->name }}</a></h2>
            <p>{{ $reward->points_cost }} points · {{ $reward->stock_quantity > 0 ? $reward->stock_quantity.' left' : 'Out of stock' }}</p>
        </article>
    @empty
        <p>No rewards yet.</p>
    @endforelse

    {{ $rewards->links() }}
@endsection
