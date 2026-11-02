{{--
    GET /admin/leaderboard/organizations — organizations ranked by the points
    earned by the students currently in them. Students only.
    API_Design.md §7.4; System_Plan.md §5.6.

    Zero scores are not listed and ties keep a stable order (Track D question 2).
--}}
@extends('layouts.admin')

@section('title', 'Organization leaderboard')

@section('content')
    <h1>Organization leaderboard</h1>

    <div class="table-region" role="region" tabindex="0" aria-label="Records">
        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col" class="numeric">Rank</th>
                    <th scope="col">Organization</th>
                    <th scope="col" class="numeric">Points earned</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($organizations as $organization)
                    <tr>
                        <td class="numeric">{{ $loop->iteration }}</td>
                        <td>{{ $organization->name }}</td>
                        <td class="numeric">{{ $organization->points_earned }}</td>
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
