{{-- GET /login. API_Design.md §7.2. --}}
@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
    <h1>Sign in</h1>

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <p>
            <label for="email">Email</label>
            <input id="email" name="email" type="email" maxlength="255" required autofocus autocomplete="username"
                   value="{{ old('email') }}">
        </p>
        <p>
            <label for="password">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password">
        </p>
        <button type="submit">Sign in</button>
    </form>
@endsection
