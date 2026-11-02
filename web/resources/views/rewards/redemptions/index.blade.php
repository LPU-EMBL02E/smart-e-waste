{{--
    GET /redemptions — the signed-in user's own redemptions.
    API_Design.md §7.3, §7.1; all-pages-wireframe.html S8.
--}}
@extends('layouts.app')

@section('title', 'My redemptions')

@section('content')
    <x-ui.page-heading title="My redemptions" />

    @if ($redemptions->isEmpty())
        <x-ui.empty title="No redemptions yet">
            <a href="{{ route('rewards.index') }}">See the rewards.</a>
        </x-ui.empty>
    @else
        <section class="section">
            <x-ui.table label="Your redemptions">
                <x-slot:head>
                    <th scope="col">Date ({{ config('ewaste.display_timezone') }})</th>
                    <th scope="col">Code</th>
                    <th scope="col">Reward</th>
                    <th scope="col" class="numeric">Qty</th>
                    <th scope="col" class="numeric">Points</th>
                    <th scope="col">Status</th>
                </x-slot:head>

                @foreach ($redemptions as $redemption)
                    <tr>
                        <td>@datetime($redemption->created_at)</td>
                        <td>{{ $redemption->redemption_code }}</td>
                        <td>{{ $redemption->reward->name }}</td>
                        <td class="numeric">{{ $redemption->quantity }}</td>
                        <td class="numeric">@points($redemption->points_cost)</td>
                        <td>
                            <x-ui.pill :status="$redemption->status" />
                            @if ($redemption->fulfilled_at)
                                <span class="muted">@date($redemption->fulfilled_at)</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            {{ $redemptions->links() }}
        </section>
    @endif
@endsection
