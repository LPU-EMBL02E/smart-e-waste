{{--
    GET /leaderboard — every user, admins included, ranked by points earned, not
    balance. API_Design.md §7.3, P31; System_Plan.md §5.6; all-pages-wireframe.html S5.

    A top list with a fixed limit, not a full table. Zero scores are not listed
    and ties keep a stable order (Track D question 2).
--}}
@extends('layouts.app')

@section('title', 'Leaderboard')

@section('content')
    <x-ui.page-heading title="Leaderboard"
                       description="Ranked by points earned from deposits. Redeeming rewards does not lower your rank." />

    @if ($users->isEmpty())
        <x-ui.empty title="No points earned yet" />
    @else
        <x-ui.table label="Leaderboard">
            <x-slot:head>
                <th scope="col" class="numeric">Rank</th>
                <th scope="col">Name</th>
                <th scope="col">Organization</th>
                <th scope="col" class="numeric">Points earned</th>
            </x-slot:head>

            @foreach ($users as $user)
                <tr>
                    <td class="numeric">{{ $loop->iteration }}</td>
                    <td>
                        @if ($user->id === auth()->id())
                            <strong>{{ $user->fullName() }} (you)</strong>
                        @else
                            {{ $user->fullName() }}
                        @endif
                    </td>
                    <td>{{ $user->organization_name ?? '—' }}</td>
                    <td class="numeric">@points($user->points_earned)</td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif
@endsection
