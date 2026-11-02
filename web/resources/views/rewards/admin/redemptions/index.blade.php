{{-- GET /admin/redemptions — every redemption, newest first. API_Design.md §7.4, §7.6. --}}
@extends('layouts.admin')

@section('title', 'Redemptions')

@section('content')
    <h1>Redemptions</h1>

    <div class="table-region" role="region" tabindex="0" aria-label="Records">
        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col">Code</th>
                    <th scope="col">Student</th>
                    <th scope="col">Reward</th>
                    <th scope="col" class="numeric">Quantity</th>
                    <th scope="col" class="numeric">Points</th>
                    <th scope="col">Status</th>
                    <th scope="col">Redeemed</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($redemptions as $redemption)
                    <tr>
                        <td>{{ $redemption->redemption_code }}</td>
                        <td>{{ $redemption->user->fullName() }}</td>
                        <td>{{ $redemption->reward->name }}</td>
                        <td class="numeric">{{ $redemption->quantity }}</td>
                        <td class="numeric">{{ $redemption->points_cost }}</td>
                        <td>{{ ucfirst(strtolower($redemption->status)) }}</td>
                        <td>{{ $redemption->created_at->timezone(config('ewaste.display_timezone'))->format('d M Y, g:ia') }}
                        </td>
                        <td>
                            @if ($redemption->canBeFulfilled())
                                <form class="app-form" method="POST"
                                    action="{{ route('admin.redemptions.fulfil', $redemption) }}">
                                    @csrf
                                    <button class="button primary" type="submit">Mark fulfilled</button>
                                </form>
                            @endif

                            @if ($redemption->canBeCancelled())
                                <form class="app-form" method="POST"
                                    action="{{ route('admin.redemptions.cancel', $redemption) }}">
                                    @csrf
                                    <label for="cancel_reason_{{ $redemption->id }}">Reason</label>
                                    <input class="form-control" type="text" id="cancel_reason_{{ $redemption->id }}"
                                        name="cancel_reason" maxlength="255" required>
                                    <button class="button danger" type="submit">Cancel and return the points</button>
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

    {{ $redemptions->links('pagination.portal') }}
@endsection
