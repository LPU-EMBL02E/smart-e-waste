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

    <div class="table-region" role="region" tabindex="0" aria-label="Records">
        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col" class="numeric">Rank</th>
                    <th scope="col">Name</th>
                    <th scope="col" class="numeric">Points earned</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td class="numeric">{{ $loop->iteration }}</td>
                        <td>{{ $user->fullName() }}</td>
                        <td class="numeric">{{ $user->points_earned }}</td>
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
