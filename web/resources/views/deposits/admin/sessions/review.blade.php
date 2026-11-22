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

    <div style="overflow-x: auto">
        <table>
            <thead>
                <tr>
                    <th>Session</th>
                    <th>Student</th>
                    <th>Bin</th>
                    <th>Weight</th>
                    <th>Verification</th>
                    <th>Opened</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sessions as $session)
                    <tr>
                        <td>{{ $session->id }}</td>
                        <td>{{ $session->user->fullName() }}</td>
                        <td>{{ $session->device->name }}</td>
                        <td>{{ $session->net_weight_g }} g</td>
                        <td>{{ $session->verification_method }} ({{ $session->verification_score ?? 'no score' }})</td>
                        <td>{{ $session->created_at->timezone(config('ewaste.display_timezone'))->format('d M Y, g:ia') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">No sessions are waiting for review.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $sessions->links() }}
@endsection
