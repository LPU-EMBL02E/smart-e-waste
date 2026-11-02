{{--
    GET /admin/sessions/review — ACCEPTED sessions whose `complete` call never
    arrived within the review threshold, oldest first. API_Design.md §7.4;
    System_Plan.md §5.3 rule 6.

    A list only. What an admin can do with one is API_Design.md §10 question 1.
--}}
@extends('layouts.admin')

@section('title', 'Sessions to review')

@section('content')
    <h1>Sessions to review</h1>

    <p>
        Accepted deposits with no actuator result after
        {{ config('ewaste.review_threshold_minutes') }} minutes. No points have been
        credited. The bin may still send the result.
    </p>

    <div class="table-region" role="region" tabindex="0" aria-label="Records">
        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col">Session</th>
                    <th scope="col">Student</th>
                    <th scope="col">Bin</th>
                    <th scope="col" class="numeric">Weight</th>
                    <th scope="col">Verification</th>
                    <th scope="col">Opened</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sessions as $session)
                    <tr>
                        <td>{{ $session->id }}</td>
                        <td>{{ $session->user->fullName() }}</td>
                        <td>{{ $session->device->name }}</td>
                        <td class="numeric">{{ $session->net_weight_g }} g</td>
                        <td>{{ $session->verification_method }} ({{ $session->verification_score ?? 'no score' }})</td>
                        <td>{{ $session->created_at->timezone(config('ewaste.display_timezone'))->format('d M Y, g:ia') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">No sessions are waiting for review.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $sessions->links('pagination.portal') }}
@endsection
