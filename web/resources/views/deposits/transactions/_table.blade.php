{{--
    A user's own transactions, shared by /transactions and the recent list on
    /home. Voided ones stay listed with their status, so a student can see why
    a balance changed (API_Design.md §7.3).

    Expects $transactions: Transaction models with `session.device` eager-loaded.
--}}
<x-ui.table label="Your deposits">
    <x-slot:head>
        <th scope="col">Date ({{ config('ewaste.display_timezone') }})</th>
        <th scope="col">Code</th>
        <th scope="col">Bin</th>
        <th scope="col" class="numeric">Weight</th>
        <th scope="col" class="numeric">Points</th>
        <th scope="col">Status</th>
    </x-slot:head>

    @foreach ($transactions as $transaction)
        <tr>
            <td>@datetime($transaction->created_at)</td>
            <td>{{ $transaction->transaction_code }}</td>
            <td>{{ $transaction->session->device->name }}</td>
            <td class="numeric">@grams($transaction->session->net_weight_g)</td>
            <td class="numeric">@points($transaction->points_awarded)</td>
            <td>
                <x-ui.pill :status="$transaction->status" />
                @if ($transaction->isVoided())
                    <span class="muted">points reversed @date($transaction->voided_at)</span>
                @endif
            </td>
        </tr>
    @endforeach
</x-ui.table>
