{{-- GET /redemptions — the signed-in user's own redemptions. API_Design.md §7.3, §7.1. --}}
@extends('layouts.app')

@section('title', 'My redemptions')

@section('content')
    <h1>My redemptions</h1>

    <div style="overflow-x: auto">
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Reward</th>
                    <th>Quantity</th>
                    <th>Points</th>
                    <th>Status</th>
                    <th>Redeemed</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($redemptions as $redemption)
                    <tr>
                        <td>{{ $redemption->redemption_code }}</td>
                        <td>{{ $redemption->reward->name }}</td>
                        <td>{{ $redemption->quantity }}</td>
                        <td>{{ $redemption->points_cost }}</td>
                        <td>{{ ucfirst(strtolower($redemption->status)) }}</td>
                        <td>{{ $redemption->created_at->timezone(config('ewaste.display_timezone'))->format('d M Y, g:ia') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">No redemptions yet. <a href="{{ route('rewards.index') }}">See the rewards.</a></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $redemptions->links() }}
@endsection
