{{-- GET /admin/transactions — every transaction, newest first. API_Design.md §7.4. --}}
@extends('layouts.admin')

@section('title', 'Transactions')

@section('content')
    <h1>Transactions</h1>

    {{-- Completed deposits only; a voided deposit drops out (System_Plan.md §5.6). --}}
    <p>Total collected weight: {{ number_format($totalWeightGrams / 1000, 2) }} kg</p>

    <div style="overflow-x: auto">
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Student</th>
                    <th>Bin</th>
                    <th>Weight</th>
                    <th>Points</th>
                    <th>Status</th>
                    <th>Completed</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($transactions as $transaction)
                    <tr>
                        <td><a href="{{ route('admin.transactions.show', $transaction) }}">{{ $transaction->transaction_code }}</a></td>
                        <td>{{ $transaction->session->user->fullName() }}</td>
                        <td>{{ $transaction->session->device->name }}</td>
                        <td>{{ $transaction->session->net_weight_g }} g</td>
                        <td>{{ $transaction->points_awarded }}</td>
                        <td>{{ ucfirst(strtolower($transaction->status)) }}</td>
                        <td>{{ $transaction->created_at->timezone(config('ewaste.display_timezone'))->format('d M Y, g:ia') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">No transactions yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $transactions->links() }}
@endsection
