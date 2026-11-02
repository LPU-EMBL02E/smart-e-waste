{{-- GET /admin/transactions — every transaction, newest first. API_Design.md §7.4. --}}
@extends('layouts.admin')

@section('title', 'Transactions')

@section('content')
    <h1>Transactions</h1>

    {{-- Completed deposits only; a voided deposit drops out (System_Plan.md §5.6). --}}
    <p>Total collected weight: {{ number_format($totalWeightGrams / 1000, 2) }} kg</p>

    <div class="table-region" role="region" tabindex="0" aria-label="Records">
        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col">Code</th>
                    <th scope="col">Student</th>
                    <th scope="col">Bin</th>
                    <th scope="col" class="numeric">Weight</th>
                    <th scope="col" class="numeric">Points</th>
                    <th scope="col">Status</th>
                    <th scope="col">Completed</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($transactions as $transaction)
                    <tr>
                        <td><a
                                href="{{ route('admin.transactions.show', $transaction) }}">{{ $transaction->transaction_code }}</a>
                        </td>
                        <td>{{ $transaction->session->user->fullName() }}</td>
                        <td>{{ $transaction->session->device->name }}</td>
                        <td class="numeric">{{ $transaction->session->net_weight_g }} g</td>
                        <td class="numeric">{{ $transaction->points_awarded }}</td>
                        <td>{{ ucfirst(strtolower($transaction->status)) }}</td>
                        <td>{{ $transaction->created_at->timezone(config('ewaste.display_timezone'))->format('d M Y, g:ia') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">No transactions yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $transactions->links('pagination.portal') }}
@endsection
