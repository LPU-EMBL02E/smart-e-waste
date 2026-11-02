{{-- GET /admin/rewards — every reward, active or not. API_Design.md §7.4, all-pages-wireframe.html A18. --}}
@extends('layouts.admin')

@section('title', 'Rewards')

@section('content')
    <x-ui.page-heading title="Rewards">
        <x-slot:actions>
            <x-ui.button variant="primary" :href="route('admin.rewards.create')">New reward</x-ui.button>
        </x-slot:actions>
    </x-ui.page-heading>

    @if ($rewards->isEmpty())
        <x-ui.empty title="No rewards yet" />
    @else
        <section class="section">
            <x-ui.table label="Rewards">
                <x-slot:head>
                    <th scope="col">Name</th>
                    <th scope="col" class="numeric">Points cost</th>
                    <th scope="col" class="numeric">Stock</th>
                    <th scope="col">Status</th>
                </x-slot:head>

                @foreach ($rewards as $reward)
                    <tr>
                        <td><a href="{{ route('admin.rewards.edit', $reward) }}">{{ $reward->name }}</a></td>
                        <td class="numeric">@wholenumber($reward->points_cost)</td>
                        <td class="numeric">@wholenumber($reward->stock_quantity)</td>
                        <td><x-ui.pill :status="$reward->is_active ? 'Active' : 'Inactive'" /></td>
                    </tr>
                @endforeach
            </x-ui.table>

            {{ $rewards->links() }}
        </section>
    @endif
@endsection
