{{--
    A user's own transactions, shared by /transactions and the recent list on
    /home. Voided ones stay listed with their status, so a student can see why
    a balance changed (API_Design.md §7.3).

    Expects $transactions: Transaction models with `session` eager-loaded.
--}}
<div class="table-region" role="region" tabindex="0" aria-label="Deposit records">
    <table class="data-table">
        <thead>
            <tr>
                <th scope="col">Code</th>
                <th scope="col" class="numeric">Weight</th>
                <th scope="col" class="numeric">Points</th>
                <th scope="col">Status</th>
                <th scope="col">Completed</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($transactions as $transaction)
                <tr>
                    <td>{{ $transaction->transaction_code }}</td>
                    <td class="numeric">{{ $transaction->session->net_weight_g }} g</td>
                    <td class="numeric">{{ $transaction->points_awarded }}</td>
                    <td><span class="status-tag">{{ ucfirst(strtolower($transaction->status)) }}</span></td>
                    <td>{{ $transaction->created_at->timezone(config('ewaste.display_timezone'))->format('d M Y, g:ia') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">No deposits yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
