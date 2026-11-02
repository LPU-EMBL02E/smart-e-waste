{{-- GET /email/verify. The only page an unconfirmed user can use (API_Design.md §7.2, P48). --}}
@extends('layouts.guest')

@section('title', 'Confirm your email')

@section('content')
    <h1>Confirm your email</h1>
    <p>
        Open the link we sent to {{ auth()->user()->email }} before using your account. Can't find it? Check your spam
        folder. The link works for {{ config('auth.verification.expire', 60) }} minutes.
    </p>

    <form class="app-form" method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button class="button primary" type="submit">Send the link again</button>
    </form>

    @include('layouts.partials.logout')
@endsection
