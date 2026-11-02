{{-- GET /home — points balance and recent deposits. API_Design.md §7.3. --}}
@extends('layouts.app')

@section('title', 'Dashboard home')

@section('content')
    <div class="page-heading">
        <h1>Dashboard home</h1>
        <p>An overview of your e-waste contributions and reward activity.</p>
    </div>

    <div class="dashboard-overview">
        {{-- SUM of the ledger; below zero after a void of spent points (System_Plan.md §6.1). --}}
        <section class="points-panel" aria-labelledby="points-heading">
            <h2 id="points-heading">Available points</h2>
            <div class="points-total">{{ $balance }} <span>pts</span></div>
            <p>Redeemable rewards balance</p>
        </section>
        <section class="dashboard-panel account-actions" aria-labelledby="account-actions-heading">
            <h2 id="account-actions-heading">Your campus account</h2>
            <p>Use your profile and QR credential for deposits, or explore the rewards catalog.</p>
            <div class="quick-actions">
                <a class="button primary" href="{{ route('rewards.index') }}">
                    @include('layouts.partials.icon', ['name' => 'rewards'])
                    Browse rewards
                </a>
                <a class="button secondary" href="{{ route('profile.show') }}">
                    @include('layouts.partials.icon', ['name' => 'qr'])
                    View your profile and QR
                </a>
            </div>
        </section>
    </div>

    <section class="dashboard-panel" aria-labelledby="recent-deposits-heading">
        <div class="panel-heading">
            <h2 id="recent-deposits-heading">Recent deposits</h2>
            <a class="text-action" href="{{ route('transactions.index') }}">View all <span aria-hidden="true">→</span></a>
        </div>
        <div class="panel-body">
            @include('deposits.transactions._table')
        </div>
    </section>
@endsection
