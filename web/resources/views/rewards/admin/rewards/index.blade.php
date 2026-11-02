{{-- GET /admin/rewards — every reward, active or not. API_Design.md §7.4. --}}
@extends('layouts.admin')

@section('title', 'Rewards')

@section('content')
    <h1>Rewards</h1>

    <p><a class="button primary" href="{{ route('admin.rewards.create') }}">New reward</a></p>

    <div class="table-region" role="region" tabindex="0" aria-label="Records">
        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col">Name</th>
                    <th scope="col">Cost</th>
                    <th scope="col" class="numeric">In stock</th>
                    <th scope="col">Shown to students</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rewards as $reward)
                    <tr>
                        <td>{{ $reward->name }}</td>
                        <td>{{ $reward->points_cost }} points</td>
                        <td class="numeric">{{ $reward->stock_quantity }}</td>
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

    {{ $rewards->links('pagination.portal') }}
@endsection
