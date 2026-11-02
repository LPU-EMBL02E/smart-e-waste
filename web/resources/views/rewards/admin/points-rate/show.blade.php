{{--
    GET /admin/points-rate — the rule in effect, the form for a new one, and every
    past rule. API_Design.md §7.4, §7.6 ("Save a points rule"), P38.

    There is no edit: saving closes the current rule and inserts a new one, so
    past transactions keep the rule they were priced with.
--}}
@extends('layouts.admin')

@section('title', 'Points rate')

@section('content')
    <h1>Points rate</h1>

    <h2>In effect now</h2>
    @if ($current)
        <dl class="details-list">
            <dt>Name</dt>
            <dd>{{ $current->name }}</dd>
            <dt>Points per gram</dt>
            <dd>{{ $current->points_per_gram }}</dd>
            <dt>Weight accepted</dt>
            <dd>{{ $current->minimum_weight_g }} g to
                {{ $current->maximum_weight_g !== null ? $current->maximum_weight_g . ' g' : 'no limit' }}</dd>
            <dt>Daily cap</dt>
            <dd>{{ $current->daily_points_cap !== null ? $current->daily_points_cap . ' points' : 'No cap' }}</dd>
        </dl>
    @else
        <p>No rule is in effect. Every bin refuses deposits until one is saved.</p>
    @endif

    <h2>Save a new rule</h2>
    <p>The new rule takes effect at once and the current one is closed.</p>

    <form class="app-form" method="POST" action="{{ route('admin.points-rate.store') }}">
        @csrf
        <p class="form-field">
            <label for="name">Name</label>
            <input class="form-control" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror
                id="name" name="name" maxlength="100" required value="{{ old('name') }}">
            @error('name')
                <span class="field-error" id="name-error">{{ $message }}</span>
            @enderror
        </p>
        <p class="form-field">
            <label for="points_per_gram">Points per gram</label>
            <input class="form-control"
                @error('points_per_gram') aria-invalid="true" aria-describedby="points_per_gram-error" @enderror
                id="points_per_gram" name="points_per_gram" type="number" step="0.0001" min="0.0001" required
                value="{{ old('points_per_gram', $current?->points_per_gram) }}">
            @error('points_per_gram')
                <span class="field-error" id="points_per_gram-error">{{ $message }}</span>
            @enderror
        </p>
        <p class="form-field">
            <label for="minimum_weight_g">Minimum weight (g)</label>
            <input class="form-control"
                @error('minimum_weight_g') aria-invalid="true" aria-describedby="minimum_weight_g-error" @enderror
                id="minimum_weight_g" name="minimum_weight_g" type="number" step="0.01" min="0.01" required
                value="{{ old('minimum_weight_g', $current?->minimum_weight_g) }}">
            @error('minimum_weight_g')
                <span class="field-error" id="minimum_weight_g-error">{{ $message }}</span>
            @enderror
        </p>
        <p class="form-field">
            <label for="maximum_weight_g">Maximum weight (g), empty for no limit</label>
            <input class="form-control"
                @error('maximum_weight_g') aria-invalid="true" aria-describedby="maximum_weight_g-error" @enderror
                id="maximum_weight_g" name="maximum_weight_g" type="number" step="0.01" min="0.01"
                value="{{ old('maximum_weight_g', $current?->maximum_weight_g) }}">
            @error('maximum_weight_g')
                <span class="field-error" id="maximum_weight_g-error">{{ $message }}</span>
            @enderror
        </p>
        <p class="form-field">
            <label for="daily_points_cap">Daily points cap, empty for no cap</label>
            <input class="form-control"
                @error('daily_points_cap') aria-invalid="true" aria-describedby="daily_points_cap-error" @enderror
                id="daily_points_cap" name="daily_points_cap" type="number" step="1" min="1"
                value="{{ old('daily_points_cap', $current?->daily_points_cap) }}">
            @error('daily_points_cap')
                <span class="field-error" id="daily_points_cap-error">{{ $message }}</span>
            @enderror
        </p>
        <button class="button primary" type="submit">Save and apply now</button>
    </form>

    <h2>All rules</h2>
    <div class="table-region" role="region" tabindex="0" aria-label="Records">
        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col">Name</th>
                    <th scope="col" class="numeric">Points per gram</th>
                    <th scope="col" class="numeric">Minimum</th>
                    <th scope="col" class="numeric">Maximum</th>
                    <th scope="col" class="numeric">Daily cap</th>
                    <th scope="col">From</th>
                    <th scope="col">To</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rules as $rule)
                    <tr>
                        <td>{{ $rule->name }}</td>
                        <td class="numeric">{{ $rule->points_per_gram }}</td>
                        <td class="numeric">{{ $rule->minimum_weight_g }} g</td>
                        <td class="numeric">
                            {{ $rule->maximum_weight_g !== null ? $rule->maximum_weight_g . ' g' : 'No limit' }}</td>
                        <td class="numeric">{{ $rule->daily_points_cap ?? 'No cap' }}</td>
                        <td>{{ $rule->effective_from->timezone(config('ewaste.display_timezone'))->format('d M Y, g:ia') }}
                        </td>
                        <td>{{ $rule->effective_to?->timezone(config('ewaste.display_timezone'))->format('d M Y, g:ia') ?? 'In effect' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">No rules yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $rules->links('pagination.portal') }}
@endsection
