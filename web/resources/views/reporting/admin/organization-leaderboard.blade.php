{{--
    GET /admin/leaderboard/organizations — organizations ranked by the points
    earned by the students currently in them. Students only.
    API_Design.md §7.4; System_Plan.md §5.6; all-pages-wireframe.html A2.

    Zero scores are not listed and ties keep a stable order (Track D question 2).
--}}
@extends('layouts.admin')

@section('title', 'Organization leaderboard')

@section('content')
    <x-ui.page-heading title="Organization leaderboard"
                       description="Total points earned by the students currently in each organization. Admins are not counted." />

    @if ($organizations->isEmpty())
        <x-ui.empty title="No points earned yet" />
    @else
        <x-ui.table label="Organization leaderboard">
            <x-slot:head>
                <th scope="col" class="numeric">Rank</th>
                <th scope="col">Organization</th>
                <th scope="col">Code</th>
                <th scope="col" class="numeric">Points earned</th>
            </x-slot:head>

            @foreach ($organizations as $organization)
                <tr>
                    <td class="numeric">{{ $loop->iteration }}</td>
                    <td>{{ $organization->name }}</td>
                    <td>{{ $organization->code }}</td>
                    <td class="numeric">@points($organization->points_earned)</td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif
@endsection
