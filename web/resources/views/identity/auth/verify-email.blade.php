{{--
    GET /email/verify. The only page an unconfirmed user can use
    (API_Design.md §7.2, P48; all-pages-wireframe.html G5).
--}}
@extends('layouts.guest')

@section('title', 'Confirm your email')

@section('content')
    <h1>Confirm your email</h1>
    <p>We sent a link to <strong>{{ auth()->user()->email }}</strong>. Open it to finish setting up your account.</p>
    <p class="small muted">
        Can't find it? Check your spam folder. The link works for {{ config('auth.verification.expire', 60) }} minutes.
    </p>

    <div class="actions">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-ui.button variant="primary">Send the link again</x-ui.button>
        </form>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-ui.button>Sign out</x-ui.button>
        </form>
    </div>
@endsection
