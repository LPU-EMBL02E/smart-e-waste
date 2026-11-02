{{--
    GET /admin/transactions/{transaction} — the evidence trail for one deposit.
    API_Design.md §7.4, all-pages-wireframe.html A11. The void form shows only
    while it is COMPLETED (§7.6).
--}}
@extends('layouts.admin')

@section('title', $transaction->transaction_code)

@php($session = $transaction->session)

@section('content')
    <x-ui.page-heading :title="$transaction->transaction_code" :back="route('admin.transactions.index')" back-label="Transactions">
        <x-slot:actions>
            <x-ui.pill :status="$transaction->status" />
        </x-slot:actions>
    </x-ui.page-heading>

    <div class="split">
        <x-ui.panel title="Transaction">
            <dl class="details-list">
                <dt>Completed</dt>
                <dd>@datetimeseconds($transaction->created_at)</dd>
                <dt>Points awarded</dt>
                <dd>@points($transaction->points_awarded)</dd>
                <dt>Rule used</dt>
                <dd>{{ $transaction->rewardRule->name }} · {{ $transaction->rewardRule->points_per_gram }} pts/g</dd>
            </dl>
        </x-ui.panel>

        <x-ui.panel title="Session">
            <dl class="details-list">
                <dt>Student</dt>
                <dd>
                    {{ $session->user->fullName() }}
                    @if ($session->user->organization)
                        ({{ $session->user->organization->code }})
                    @endif
                </dd>
                <dt>Bin</dt>
                <dd><a href="{{ route('admin.bins.show', $session->device) }}">{{ $session->device->name }}</a></dd>
                <dt>Opened</dt>
                <dd>@datetimeseconds($session->created_at)</dd>
                <dt>Closed</dt>
                <dd>@datetimeseconds($session->closed_at)</dd>
                <dt>Session id</dt>
                <dd><code>{{ $session->id }}</code></dd>
            </dl>
        </x-ui.panel>

        <x-ui.panel title="Weight">
            <dl class="details-list">
                <dt>Net weight</dt>
                <dd>@grams($session->net_weight_g)</dd>
                <dt>Stable</dt>
                <dd>{{ $session->is_weight_stable ? 'Yes' : 'No' }}</dd>
                <dt>Samples</dt>
                <dd>{{ $session->sample_count }}</dd>
            </dl>
        </x-ui.panel>

        <x-ui.panel title="Camera check and actuator">
            <dl class="details-list">
                <dt>Method</dt>
                <dd><code>{{ $session->verification_method }}</code></dd>
                <dt>Passed</dt>
                <dd>{{ $session->verification_passed ? 'Yes' : 'No' }}</dd>
                <dt>Score</dt>
                <dd>{{ $session->verification_score ?? '—' }}</dd>
                <dt>Actuator OK</dt>
                <dd>{{ $session->actuator_ok ? 'Yes' : 'No' }}</dd>
                <dt>Cycle</dt>
                <dd>{{ $session->actuator_cycle_ms !== null ? number_format($session->actuator_cycle_ms).' ms' : '—' }}</dd>
            </dl>
        </x-ui.panel>
    </div>

    @if ($transaction->isVoided())
        <x-ui.panel title="Void">
            <dl class="details-list">
                <dt>Reason</dt>
                <dd>{{ $transaction->void_reason }}</dd>
                <dt>Voided by</dt>
                <dd>{{ $transaction->voidedBy?->fullName() ?? '—' }}</dd>
                <dt>Voided at</dt>
                <dd>@datetime($transaction->voided_at)</dd>
            </dl>
        </x-ui.panel>
    @endif

    @if ($transaction->canBeVoided())
        <x-ui.panel title="Void this transaction" class="panel-danger panel-narrow">
            <form class="form" method="POST" action="{{ route('admin.transactions.void', $transaction) }}">
                @csrf
                <p>
                    {{ $session->user->fullName() }} loses @points($transaction->points_awarded). Their balance may go
                    below zero. This can't be undone.
                </p>
                <x-form.textarea name="void_reason" label="Reason" maxlength="500" required hint="Up to 500 characters." />
                <div class="actions">
                    <x-ui.button variant="danger">Void transaction</x-ui.button>
                </div>
            </form>
        </x-ui.panel>
    @endif
@endsection
