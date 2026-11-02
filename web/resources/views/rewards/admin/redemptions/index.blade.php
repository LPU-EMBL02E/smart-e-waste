{{-- GET /admin/redemptions — every redemption, newest first. API_Design.md §7.4, §7.6; all-pages-wireframe.html A21. --}}
@extends('layouts.admin')

@section('title', 'Redemptions')

@section('content')
    <x-ui.page-heading title="Redemptions"
                       description="Cancel returns the points and the stock. A fulfilled redemption can't be cancelled." />

    @if ($redemptions->isEmpty())
        <x-ui.empty title="No redemptions yet" />
    @else
        <section class="section">
            <x-ui.table label="Redemptions">
                <x-slot:head>
                    <th scope="col">Date ({{ config('ewaste.display_timezone') }})</th>
                    <th scope="col">Code</th>
                    <th scope="col">Student</th>
                    <th scope="col">Org</th>
                    <th scope="col">Reward</th>
                    <th scope="col" class="numeric">Qty</th>
                    <th scope="col" class="numeric">Points</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                </x-slot:head>

                @foreach ($redemptions as $redemption)
                    <tr>
                        <td>@datetime($redemption->created_at)</td>
                        <td>{{ $redemption->redemption_code }}</td>
                        <td>{{ $redemption->user->fullName() }}</td>
                        <td>{{ $redemption->user->organization?->code ?? '—' }}</td>
                        <td>{{ $redemption->reward->name }}</td>
                        <td class="numeric">{{ $redemption->quantity }}</td>
                        <td class="numeric">@wholenumber($redemption->points_cost)</td>
                        <td><x-ui.pill :status="$redemption->status" /></td>
                        <td>
                            @if ($redemption->fulfilled_at)
                                <span class="muted">@date($redemption->fulfilled_at)</span>
                            @endif

                            @if ($redemption->canBeFulfilled() || $redemption->canBeCancelled())
                                <div class="table-actions">
                                    @if ($redemption->canBeFulfilled())
                                        <form method="POST" action="{{ route('admin.redemptions.fulfil', $redemption) }}">
                                            @csrf
                                            <x-ui.button variant="primary">Mark fulfilled</x-ui.button>
                                        </form>
                                    @endif

                                    @if ($redemption->canBeCancelled())
                                        <details class="table-disclosure" @if ($errors->has('cancel_reason') && (int) old('redemption_id') === $redemption->id) open @endif>
                                            <summary class="button button-danger">Cancel</summary>
                                            <form method="POST" action="{{ route('admin.redemptions.cancel', $redemption) }}">
                                                @csrf
                                                <input type="hidden" name="redemption_id" value="{{ $redemption->id }}">
                                                <x-form.input name="cancel_reason" :id="'cancel_reason_'.$redemption->id" label="Reason" maxlength="255" required
                                                              :value="(int) old('redemption_id') === $redemption->id ? old('cancel_reason') : null" />
                                                <x-ui.button variant="danger">Cancel redemption</x-ui.button>
                                            </form>
                                        </details>
                                    @endif
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            {{ $redemptions->links() }}
        </section>
    @endif
@endsection
