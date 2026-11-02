{{--
    GET /admin/sessions/review — ACCEPTED sessions whose `complete` call never
    arrived within the review threshold, oldest first. API_Design.md §7.4;
    System_Plan.md §5.3 rule 6; all-pages-wireframe.html A12.

    A list only. What an admin can do with one is API_Design.md §10 question 1.
--}}
@extends('layouts.admin')

@section('title', 'Session review')

@section('content')
    <x-ui.page-heading title="Session review"
                       :description="'Accepted deposits with no actuator result after '.config('ewaste.review_threshold_minutes').' minutes. No points have been credited. The bin may still send the result.'" />

    @if ($sessions->isEmpty())
        <x-ui.empty title="No sessions are waiting for review" />
    @else
        <section class="section">
            <x-ui.table label="Sessions awaiting review">
                <x-slot:head>
                    <th scope="col">Session</th>
                    <th scope="col">Student</th>
                    <th scope="col">Bin</th>
                    <th scope="col">Opened ({{ config('ewaste.display_timezone') }})</th>
                    <th scope="col">Waiting for</th>
                    <th scope="col" class="numeric">Weight</th>
                    <th scope="col">Method</th>
                    <th scope="col" class="numeric">Camera score</th>
                </x-slot:head>

                @foreach ($sessions as $session)
                    <tr>
                        <td><code>{{ $session->id }}</code></td>
                        <td>{{ $session->user->fullName() }}</td>
                        <td>{{ $session->device->name }}</td>
                        <td>@datetime($session->created_at)</td>
                        <td>{{ $session->created_at->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE, parts: 2) }}</td>
                        <td class="numeric">@grams($session->net_weight_g)</td>
                        <td><code>{{ $session->verification_method }}</code></td>
                        <td class="numeric">{{ $session->verification_score ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-ui.table>

            {{ $sessions->links() }}

            <p class="small muted">No actions yet: what an admin may do with these is still to be decided.</p>
        </section>
    @endif
@endsection
