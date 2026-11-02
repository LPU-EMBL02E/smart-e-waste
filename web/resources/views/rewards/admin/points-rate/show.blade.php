{{--
    GET /admin/points-rate — the rule in effect, the form for a new one, and every
    past rule. API_Design.md §7.4, §7.6 ("Save a points rule"), P38;
    all-pages-wireframe.html A22.

    There is no edit: saving closes the current rule and inserts a new one, so
    past transactions keep the rule they were priced with.
--}}
@extends('layouts.admin')

@section('title', 'Points rate')

@section('content')
    <x-ui.page-heading title="Points rate" />

    @if ($current)
        <x-ui.panel title="Current rule">
            <dl class="details-list">
                <dt>Name</dt>
                <dd>{{ $current->name }}</dd>
                <dt>Points per gram</dt>
                <dd>{{ $current->points_per_gram }}</dd>
                <dt>Minimum weight</dt>
                <dd>@grams($current->minimum_weight_g)</dd>
                <dt>Maximum weight</dt>
                <dd>{{ $current->maximum_weight_g !== null ? \App\Support\Display::grams($current->maximum_weight_g) : 'No limit' }}</dd>
                <dt>Daily cap</dt>
                <dd>{{ $current->daily_points_cap !== null ? \App\Support\Display::points($current->daily_points_cap).' per student' : 'No cap' }}</dd>
                <dt>In effect since</dt>
                <dd>@datetime($current->effective_from)</dd>
            </dl>
        </x-ui.panel>
    @else
        <div class="feedback" role="status">
            No points rule is in effect. The bin refuses every deposit until you save one.
        </div>
    @endif

    <x-ui.panel title="Save a new rule" class="panel-narrow">
        <form class="form" method="POST" action="{{ route('admin.points-rate.store') }}">
            @csrf
            <x-form.input name="name" label="Name" maxlength="100" required />
            <div class="form-row">
                <x-form.input name="points_per_gram" label="Points per gram" type="number" step="0.0001" min="0.0001" required
                              :value="$current?->points_per_gram" hint="Above 0." />
                <x-form.input name="minimum_weight_g" label="Minimum weight (g)" type="number" step="0.01" min="0.01" required
                              :value="$current?->minimum_weight_g" hint="At least 1 ÷ points per gram, so a deposit never earns 0." />
            </div>
            <div class="form-row">
                <x-form.input name="maximum_weight_g" label="Maximum weight (g)" type="number" step="0.01" min="0.01"
                              :value="$current?->maximum_weight_g"
                              hint="Above the minimum, up to the bin's hardware limit. Empty = no limit." />
                <x-form.input name="daily_points_cap" label="Daily points cap" type="number" step="1" min="1"
                              :value="$current?->daily_points_cap" hint="Whole number, 1 or more. Empty = no cap." />
            </div>
            <p class="hint">
                The new rule starts now and the current one ends. Deposits already in progress keep the rule they started under.
            </p>
            <div class="actions">
                <x-ui.button variant="primary">Save new rule</x-ui.button>
            </div>
        </form>
    </x-ui.panel>

    <section class="section">
        <h2>All rules</h2>

        @if ($rules->isEmpty())
            <x-ui.empty title="No rules yet" />
        @else
            <x-ui.table label="All rules">
                <x-slot:head>
                    <th scope="col">Name</th>
                    <th scope="col" class="numeric">Pts/g</th>
                    <th scope="col" class="numeric">Min</th>
                    <th scope="col" class="numeric">Max</th>
                    <th scope="col" class="numeric">Daily cap</th>
                    <th scope="col">From ({{ config('ewaste.display_timezone') }})</th>
                    <th scope="col">To</th>
                </x-slot:head>

                @foreach ($rules as $rule)
                    <tr>
                        <td>{{ $rule->name }}</td>
                        <td class="numeric">{{ $rule->points_per_gram }}</td>
                        <td class="numeric">@grams($rule->minimum_weight_g)</td>
                        <td class="numeric">{{ $rule->maximum_weight_g !== null ? \App\Support\Display::grams($rule->maximum_weight_g) : 'No limit' }}</td>
                        <td class="numeric">{{ $rule->daily_points_cap ?? 'No cap' }}</td>
                        <td>@datetime($rule->effective_from)</td>
                        <td>@datetime($rule->effective_to, 'In effect')</td>
                    </tr>
                @endforeach
            </x-ui.table>

            {{ $rules->links() }}
        @endif
    </section>
@endsection
