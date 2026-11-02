{{-- GET /redemptions — the signed-in user's own redemptions. API_Design.md §7.3, §7.1. --}}
@extends('layouts.app')

@section('title', 'My redemptions')

@section('content')
    <h1>My redemptions</h1>

    <div class="table-region" role="region" tabindex="0" aria-label="Records">
        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col">Code</th>
                    <th scope="col">Reward</th>
                    <th scope="col" class="numeric">Quantity</th>
                    <th scope="col" class="numeric">Points</th>
                    <th scope="col">Status</th>
                    <th scope="col">Redeemed</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($redemptions as $redemption)
                    <tr>
                        <td>{{ $redemption->redemption_code }}</td>
                        <td>{{ $redemption->reward->name }}</td>
                        <td class="numeric">{{ $redemption->quantity }}</td>
                        <td class="numeric">{{ $redemption->points_cost }}</td>
                        <td>{{ ucfirst(strtolower($redemption->status)) }}</td>
                        <td>{{ $redemption->created_at->timezone(config('ewaste.display_timezone'))->format('d M Y, g:ia') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">No redemptions yet. <a href="{{ route('rewards.index') }}">See the rewards.</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $redemptions->links('pagination.portal') }}
@endsection
