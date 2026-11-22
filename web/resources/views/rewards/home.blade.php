{{-- GET /home — points balance and recent deposits. API_Design.md §7.3. --}}
@extends('layouts.app')

@section('title', 'Home')

@section('content')
    <h1>Home</h1>

    {{-- SUM of the ledger; below zero after a void of spent points (System_Plan.md §6.1). --}}
    <p>Points balance: {{ $balance }}</p>

    <h2>Recent deposits</h2>

    @include('deposits.transactions._table')

    @if ($transactions->hasMorePages())
        <p><a href="{{ route('transactions.index') }}">See all transactions</a></p>
    @endif
@endsection
