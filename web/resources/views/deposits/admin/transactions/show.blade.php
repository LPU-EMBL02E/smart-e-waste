{{--
    GET /admin/transactions/{transaction} — the evidence trail for one deposit.
    API_Design.md §7.4. The void form shows only while it is COMPLETED (§7.6).
--}}
@extends('layouts.admin')

@section('title', $transaction->transaction_code)

@php($session = $transaction->session)

@section('content')
    <h1>{{ $transaction->transaction_code }}</h1>

    <dl>
        <dt>Status</dt>
        <dd>{{ ucfirst(strtolower($transaction->status)) }}</dd>
        <dt>Student</dt>
        <dd>{{ $session->user->fullName() }}</dd>
        <dt>Bin</dt>
        <dd>{{ $session->device->name }}</dd>
        <dt>Points awarded</dt>
        <dd>{{ $transaction->points_awarded }}</dd>
        <dt>Completed</dt>
        <dd>{{ $transaction->created_at->timezone(config('ewaste.display_timezone'))->format('d M Y, g:i:sa') }}</dd>
    </dl>

    <h2>Weight</h2>
    <dl>
        <dt>Net weight</dt>
        <dd>{{ $session->net_weight_g }} g</dd>
        <dt>Samples</dt>
        <dd>{{ $session->sample_count }}</dd>
        <dt>Stable</dt>
        <dd>{{ $session->is_weight_stable ? 'Yes' : 'No' }}</dd>
    </dl>

    <h2>Verification</h2>
    <dl>
        <dt>Method</dt>
        <dd>{{ $session->verification_method }}</dd>
        <dt>Passed</dt>
        <dd>{{ $session->verification_passed ? 'Yes' : 'No' }}</dd>
        <dt>Score</dt>
        <dd>{{ $session->verification_score ?? '—' }}</dd>
    </dl>

    <h2>Actuator</h2>
    <dl>
        <dt>Confirmed</dt>
        <dd>{{ $session->actuator_ok ? 'Yes' : 'No' }}</dd>
        <dt>Cycle time</dt>
        <dd>{{ $session->actuator_cycle_ms !== null ? $session->actuator_cycle_ms.' ms' : '—' }}</dd>
    </dl>

    @if ($transaction->isVoided())
        <h2>Voided</h2>
        <dl>
            <dt>By</dt>
            <dd>{{ $transaction->voidedBy?->fullName() ?? '—' }}</dd>
            <dt>At</dt>
            <dd>{{ $transaction->voided_at?->timezone(config('ewaste.display_timezone'))->format('d M Y, g:ia') ?? '—' }}</dd>
            <dt>Reason</dt>
            <dd>{{ $transaction->void_reason }}</dd>
        </dl>
    @endif

    @if ($transaction->canBeVoided())
        <h2>Void this transaction</h2>
        <p>The points are reversed with a new ledger row. The student's balance may go below zero.</p>
        <form method="POST" action="{{ route('admin.transactions.void', $transaction) }}">
            @csrf
            <label for="void_reason">Reason</label>
            <textarea id="void_reason" name="void_reason" maxlength="500" required>{{ old('void_reason') }}</textarea>
            <button type="submit">Void</button>
        </form>
    @endif

    <p><a href="{{ route('admin.transactions.index') }}">Back to transactions</a></p>
@endsection
