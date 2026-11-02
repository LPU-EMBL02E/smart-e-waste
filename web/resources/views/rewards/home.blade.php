{{--
    GET /home — points balance and recent deposits. API_Design.md §7.3,
    web/docs/DESIGN.md §5, all-pages-wireframe.html S2.
--}}
@extends('layouts.app')

@section('title', 'Home')

@section('content')
    <x-ui.page-heading :title="'Hi, '.auth()->user()->displayName()" />

    <div class="home-top">
        {{-- SUM of the ledger; below zero after a void of spent points (System_Plan.md §6.1). --}}
        <x-ui.focal-panel label="Available points" unit="pts" foot="Redeemable rewards balance">
            @wholenumber($balance)
        </x-ui.focal-panel>

        <div class="home-actions">
            <x-ui.button variant="primary" :href="route('profile.show')">View QR code</x-ui.button>
            <a class="text-link" href="{{ route('rewards.index') }}">Browse rewards</a>
        </div>
    </div>

    <section class="section">
        <h2>Recent transactions</h2>

        @if ($transactions->isEmpty())
            <x-ui.empty title="No deposits yet">Show your QR code at the bin to earn points.</x-ui.empty>
        @else
            @include('deposits.transactions._table')
            <a class="text-link" href="{{ route('transactions.index') }}">See all transactions</a>
        @endif
    </section>
@endsection
