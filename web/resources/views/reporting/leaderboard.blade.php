{{--
    GET /leaderboard — every user, admins included, ranked by points earned, not
    balance. API_Design.md §7.3, P31; System_Plan.md §5.6.

    A top list with a fixed limit, not a full table. Zero scores are not listed
    and ties keep a stable order (Track D question 2).
--}}
@extends('layouts.app')

@section('title', 'Leaderboard')

@section('content')
    <h1>Leaderboard</h1>

    <div style="overflow-x: auto">
        <table>
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Name</th>
                    <th>Points earned</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $user->fullName() }}</td>
                        <td>{{ $user->points_earned }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3">No points earned yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
