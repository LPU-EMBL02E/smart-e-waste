{{-- GET /rewards — the catalogue of active rewards. API_Design.md §7.3, all-pages-wireframe.html S6. --}}
@extends('layouts.app')

@section('title', 'Rewards')

@section('content')
    <x-ui.page-heading title="Rewards">
        <x-slot:actions>
            <p>You have <strong>@points($balance)</strong> to spend.</p>
        </x-slot:actions>
    </x-ui.page-heading>

    @if ($rewards->isEmpty())
        <x-ui.empty title="No rewards are available right now">Check back later.</x-ui.empty>
    @else
        <section class="section">
            <div class="reward-grid">
                @foreach ($rewards as $reward)
                    <article class="panel reward-card">
                        <h2>{{ $reward->name }}</h2>
                        @if ($reward->description)
                            <p class="small muted">{{ $reward->description }}</p>
                        @endif
                        <p class="reward-cost">@points($reward->points_cost)</p>
                        <p class="small">
                            @if ($reward->stock_quantity > 0)
                                {{ $reward->stock_quantity }} left
                            @else
                                <strong>Out of stock</strong>
                            @endif
                        </p>
                        <a class="text-link" href="{{ route('rewards.show', $reward) }}">View reward</a>
                    </article>
                @endforeach
            </div>

            {{ $rewards->links() }}
        </section>
    @endif
@endsection
