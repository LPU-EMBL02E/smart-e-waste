{{-- GET /admin/rewards — every reward, active or not. API_Design.md §7.4. --}}
@extends('layouts.admin')

@section('title', 'Rewards')

@section('content')
    <h1>Rewards</h1>

    <p><a href="{{ route('admin.rewards.create') }}">New reward</a></p>

    <div style="overflow-x: auto">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Cost</th>
                    <th>In stock</th>
                    <th>Shown to students</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rewards as $reward)
                    <tr>
                        <td>{{ $reward->name }}</td>
                        <td>{{ $reward->points_cost }} points</td>
                        <td>{{ $reward->stock_quantity }}</td>
                        <td>{{ $reward->is_active ? 'Yes' : 'No' }}</td>
                        <td><a href="{{ route('admin.rewards.edit', $reward) }}">Edit</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">No rewards yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $rewards->links() }}
@endsection
