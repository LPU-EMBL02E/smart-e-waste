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

    <dl class="details-list">
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
        <form class="app-form" method="POST" action="{{ route('redemptions.store') }}">
            @csrf
            <input type="hidden" name="reward_id" value="{{ $reward->id }}">
            <p class="form-field">
                <label for="quantity">Quantity</label>
                <input class="form-control"
                    @error('quantity') aria-invalid="true" aria-describedby="quantity-error" @enderror id="quantity"
                    name="quantity" type="number" step="1" min="1" required value="{{ old('quantity', 1) }}">
                @error('quantity')
                    <span class="field-error" id="quantity-error">{{ $message }}</span>
                @enderror
            </p>
            <button class="button primary" type="submit">Redeem</button>
        </form>
    @endif

    <p><a href="{{ route('rewards.index') }}">Back to rewards</a></p>
@endsection
