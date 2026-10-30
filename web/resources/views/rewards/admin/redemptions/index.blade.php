{{-- GET /admin/redemptions — every redemption, newest first. API_Design.md §7.4, §7.6. --}}
@extends('layouts.admin')

@section('title', 'Redemptions')

@section('content')
    <h1>Redemptions</h1>

    <div style="overflow-x: auto">
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Student</th>
                    <th>Reward</th>
                    <th>Quantity</th>
                    <th>Points</th>
                    <th>Status</th>
                    <th>Redeemed</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($redemptions as $redemption)
                    <tr>
                        <td>{{ $redemption->redemption_code }}</td>
                        <td>{{ $redemption->user->fullName() }}</td>
                        <td>{{ $redemption->reward->name }}</td>
                        <td>{{ $redemption->quantity }}</td>
                        <td>{{ $redemption->points_cost }}</td>
                        <td>{{ ucfirst(strtolower($redemption->status)) }}</td>
                        <td>{{ $redemption->created_at->timezone(config('ewaste.display_timezone'))->format('d M Y, g:ia') }}</td>
                        <td>
                            @if ($redemption->canBeFulfilled())
                                <form method="POST" action="{{ route('admin.redemptions.fulfil', $redemption) }}">
                                    @csrf
                                    <button type="submit">Mark fulfilled</button>
                                </form>
                            @endif

                            @if ($redemption->canBeCancelled())
                                <form method="POST" action="{{ route('admin.redemptions.cancel', $redemption) }}">
                                    @csrf
                                    <label for="cancel_reason_{{ $redemption->id }}">Reason</label>
                                    <input type="text" id="cancel_reason_{{ $redemption->id }}" name="cancel_reason" maxlength="255" required>
                                    <button type="submit">Cancel and return the points</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">No redemptions yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $redemptions->links() }}
@endsection
