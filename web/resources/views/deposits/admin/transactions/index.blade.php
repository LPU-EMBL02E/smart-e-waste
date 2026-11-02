{{-- GET /admin/transactions — every transaction, newest first. API_Design.md §7.4, all-pages-wireframe.html A10. --}}
@extends('layouts.admin')

@section('title', 'Transactions')

@section('content')
    <x-ui.page-heading title="Transactions" />

    {{-- Completed deposits only; a voided deposit drops out (System_Plan.md §5.6). --}}
    <x-ui.focal-panel label="Total collected weight" class="panel-narrow" foot="Voided deposits excluded">
        @kilograms($totalWeightGrams)
    </x-ui.focal-panel>

    @if ($transactions->isEmpty())
        <x-ui.empty title="No transactions yet" />
    @else
        <section class="section">
            <x-ui.table label="Transactions">
                <x-slot:head>
                    <th scope="col">Date ({{ config('ewaste.display_timezone') }})</th>
                    <th scope="col">Code</th>
                    <th scope="col">User</th>
                    <th scope="col">Organization</th>
                    <th scope="col">Bin</th>
                    <th scope="col" class="numeric">Weight</th>
                    <th scope="col" class="numeric">Points</th>
                    <th scope="col">Status</th>
                </x-slot:head>

                @foreach ($transactions as $transaction)
                    <tr>
                        <td>@datetime($transaction->created_at)</td>
                        <td><a href="{{ route('admin.transactions.show', $transaction) }}">{{ $transaction->transaction_code }}</a></td>
                        <td>{{ $transaction->session->user->fullName() }}</td>
                        <td>{{ $transaction->session->user->organization?->code ?? '—' }}</td>
                        <td>{{ $transaction->session->device->name }}</td>
                        <td class="numeric">@grams($transaction->session->net_weight_g)</td>
                        <td class="numeric">@wholenumber($transaction->points_awarded)</td>
                        <td><x-ui.pill :status="$transaction->status" /></td>
                    </tr>
                @endforeach
            </x-ui.table>

            {{ $transactions->links() }}
        </section>
    @endif
@endsection
