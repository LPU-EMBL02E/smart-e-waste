{{--
    GET /profile. API_Design.md §7.3, P32, P48.

    The token is text until the team picks a QR package (§10 question 8). Details
    are read-only until §10 question 3 is decided.
--}}
@extends('layouts.app')

@section('title', 'Profile')

@section('content')
    <h1>Profile</h1>

    <h2>Your QR code</h2>
    <p><code>{{ $user->qrCredential->token }}</code></p>
    <p>
        This is the text your QR code holds. The picture of the code isn't available yet. It identifies you for one
        deposit; it can't spend points.
    </p>
    <p>
        Last changed
        {{ $user->qrCredential->updated_at->timezone(config('ewaste.display_timezone'))->format('d M Y, g:ia') }}.
    </p>
    <form method="POST" action="{{ route('profile.qr.regenerate') }}">
        @csrf
        <button type="submit">Make a new QR code</button>
    </form>
    <p>Your old code stops working at once. Use this if your code was shared or photographed.</p>

    <h2>Your details</h2>
    <dl>
        <dt>Name</dt>
        <dd>{{ $user->fullName() }}</dd>
        <dt>Email</dt>
        <dd>{{ $user->email }}</dd>
        <dt>Student number</dt>
        <dd>{{ $user->student_number ?? '—' }}</dd>
        <dt>Role</dt>
        <dd>{{ ucfirst($user->role) }}</dd>
        <dt>Member since</dt>
        <dd>{{ $user->created_at->timezone(config('ewaste.display_timezone'))->format('d M Y') }}</dd>
    </dl>

    <h2>Organization</h2>
    @if ($user->organization)
        <dl>
            <dt>Name</dt>
            <dd>{{ $user->organization->name }}</dd>
            <dt>Code</dt>
            <dd>{{ $user->organization->code }}</dd>
            <dt>About</dt>
            <dd>{{ $user->organization->description ?? '—' }}</dd>
        </dl>
    @else
        <p>No organization (admin account)</p>
    @endif

    {{-- Forgot password is a guest page, so a signed-in user has to sign out to reach it. --}}
    <p>To change your details, contact an admin. To change your password, sign out and use Forgot password.</p>
@endsection
