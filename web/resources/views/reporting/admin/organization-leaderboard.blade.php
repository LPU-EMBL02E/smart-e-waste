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

    <div style="overflow-x: auto">
        <table>
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Organization</th>
                    <th>Points earned</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($organizations as $organization)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $organization->name }}</td>
                        <td>{{ $organization->points_earned }}</td>
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
