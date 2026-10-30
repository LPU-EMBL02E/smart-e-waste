{{--
    A user's own transactions, shared by /transactions and the recent list on
    /home. Voided ones stay listed with their status, so a student can see why
    a balance changed (API_Design.md §7.3).

    Expects $transactions: Transaction models with `session` eager-loaded.
--}}
<div style="overflow-x: auto">
    <table>
        <thead>
            <tr>
                <th>Code</th>
                <th>Weight</th>
                <th>Points</th>
                <th>Status</th>
                <th>Completed</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($transactions as $transaction)
                <tr>
                    <td>{{ $transaction->transaction_code }}</td>
                    <td>{{ $transaction->session->net_weight_g }} g</td>
                    <td>{{ $transaction->points_awarded }}</td>
                    <td>{{ ucfirst(strtolower($transaction->status)) }}</td>
                    <td>{{ $transaction->created_at->timezone(config('ewaste.display_timezone'))->format('d M Y, g:ia') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">No deposits yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
