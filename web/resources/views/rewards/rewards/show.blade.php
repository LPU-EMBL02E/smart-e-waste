{{--
    GET /rewards/{reward} — one reward, with the redeem form. API_Design.md §7.3.

    "Can you afford it" is a hint from the balance; RedemptionService re-checks
    both the balance and the stock under a lock when the form is sent.
--}}
@extends('layouts.app')

@section('title', $reward->name)

@section('content')
    <h1>{{ $reward->name }}</h1>

    @if ($reward->description)
        <p>{{ $reward->description }}</p>
    @endif

    <dl>
        <dt>Cost</dt>
        <dd>{{ $reward->points_cost }} points each</dd>
        <dt>In stock</dt>
        <dd>{{ $reward->stock_quantity }}</dd>
        <dt>Your balance</dt>
        <dd>{{ $balance }} points</dd>
    </dl>

    @if ($reward->stock_quantity < 1)
        <p>Out of stock.</p>
    @elseif ($balance < $reward->points_cost)
        <p>You need {{ $reward->points_cost - $balance }} more points to redeem this.</p>
    @else
        <form method="POST" action="{{ route('redemptions.store') }}">
            @csrf
            <input type="hidden" name="reward_id" value="{{ $reward->id }}">
            <p>
                <label for="quantity">Quantity</label>
                <input id="quantity" name="quantity" type="number" step="1" min="1" required
                       value="{{ old('quantity', 1) }}">
            </p>
            <button type="submit">Redeem</button>
        </form>
    @endif

    <p><a href="{{ route('rewards.index') }}">Back to rewards</a></p>
@endsection
