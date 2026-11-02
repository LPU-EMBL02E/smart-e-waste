{{--
    GET /rewards/{reward} — one reward, with the redeem form. API_Design.md §7.3,
    all-pages-wireframe.html S7.

    "Can you afford it" is a hint from the balance; RedemptionService re-checks
    both the balance and the stock under a lock when the form is sent.
--}}
@extends('layouts.app')

@section('title', $reward->name)

@section('content')
    <x-ui.page-heading :title="$reward->name" :back="route('rewards.index')" back-label="All rewards" />

    <div class="split">
        <section class="section">
            @if ($reward->description)
                <p class="prose">{{ $reward->description }}</p>
            @endif

            <dl class="details-list">
                <dt>Cost</dt>
                <dd>@points($reward->points_cost) each</dd>
                <dt>In stock</dt>
                <dd>{{ $reward->stock_quantity }}</dd>
                <dt>Your points</dt>
                <dd>@points($balance)</dd>
            </dl>
        </section>

        <x-ui.panel title="Redeem">
            @if ($reward->stock_quantity < 1)
                <div class="actions">
                    <x-ui.button variant="primary" disabled>Redeem reward</x-ui.button>
                    <p class="small">Out of stock.</p>
                </div>
            @elseif ($balance < $reward->points_cost)
                <div class="actions">
                    <x-ui.button variant="primary" disabled>Redeem reward</x-ui.button>
                    <p class="small">You need @points($reward->points_cost - $balance) more to redeem this.</p>
                </div>
            @else
                <form class="form" method="POST" action="{{ route('redemptions.store') }}">
                    @csrf
                    <input type="hidden" name="reward_id" value="{{ $reward->id }}">
                    <x-form.input name="quantity" label="Quantity" type="number" step="1" min="1" required :value="1"
                                  hint="Whole number, 1 or more." />
                    <x-ui.button variant="primary">Redeem reward</x-ui.button>
                </form>
            @endif
        </x-ui.panel>
    </div>
@endsection
