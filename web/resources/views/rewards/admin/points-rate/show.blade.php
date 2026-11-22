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
        <dl>
            <dt>Name</dt>
            <dd>{{ $current->name }}</dd>
            <dt>Points per gram</dt>
            <dd>{{ $current->points_per_gram }}</dd>
            <dt>Weight accepted</dt>
            <dd>{{ $current->minimum_weight_g }} g to {{ $current->maximum_weight_g !== null ? $current->maximum_weight_g.' g' : 'no limit' }}</dd>
            <dt>Daily cap</dt>
            <dd>{{ $current->daily_points_cap !== null ? $current->daily_points_cap.' points' : 'No cap' }}</dd>
        </dl>
    @else
        <p>No rule is in effect. Every bin refuses deposits until one is saved.</p>
    @endif

    <h2>Save a new rule</h2>
    <p>The new rule takes effect at once and the current one is closed.</p>

    <form method="POST" action="{{ route('admin.points-rate.store') }}">
        @csrf
        <p>
            <label for="name">Name</label>
            <input id="name" name="name" maxlength="100" required value="{{ old('name') }}">
        </p>
        <p>
            <label for="points_per_gram">Points per gram</label>
            <input id="points_per_gram" name="points_per_gram" type="number" step="0.0001" min="0.0001" required
                   value="{{ old('points_per_gram', $current?->points_per_gram) }}">
        </p>
        <p>
            <label for="minimum_weight_g">Minimum weight (g)</label>
            <input id="minimum_weight_g" name="minimum_weight_g" type="number" step="0.01" min="0.01" required
                   value="{{ old('minimum_weight_g', $current?->minimum_weight_g) }}">
        </p>
        <p>
            <label for="maximum_weight_g">Maximum weight (g), empty for no limit</label>
            <input id="maximum_weight_g" name="maximum_weight_g" type="number" step="0.01" min="0.01"
                   value="{{ old('maximum_weight_g', $current?->maximum_weight_g) }}">
        </p>
        <p>
            <label for="daily_points_cap">Daily points cap, empty for no cap</label>
            <input id="daily_points_cap" name="daily_points_cap" type="number" step="1" min="1"
                   value="{{ old('daily_points_cap', $current?->daily_points_cap) }}">
        </p>
        <button type="submit">Save and apply now</button>
    </form>

    <h2>All rules</h2>
    <div style="overflow-x: auto">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Points per gram</th>
                    <th>Minimum</th>
                    <th>Maximum</th>
                    <th>Daily cap</th>
                    <th>From</th>
                    <th>To</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rules as $rule)
                    <tr>
                        <td>{{ $rule->name }}</td>
                        <td>{{ $rule->points_per_gram }}</td>
                        <td>{{ $rule->minimum_weight_g }} g</td>
                        <td>{{ $rule->maximum_weight_g !== null ? $rule->maximum_weight_g.' g' : 'No limit' }}</td>
                        <td>{{ $rule->daily_points_cap ?? 'No cap' }}</td>
                        <td>{{ $rule->effective_from->timezone(config('ewaste.display_timezone'))->format('d M Y, g:ia') }}</td>
                        <td>{{ $rule->effective_to?->timezone(config('ewaste.display_timezone'))->format('d M Y, g:ia') ?? 'In effect' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">No rules yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $rules->links() }}
@endsection
