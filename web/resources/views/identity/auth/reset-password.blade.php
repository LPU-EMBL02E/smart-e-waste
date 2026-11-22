{{-- GET /reset-password/{token}. Both emails link here. API_Design.md §7.2, P41. --}}
@extends('layouts.guest')

@section('title', 'Set your password')

@section('content')
    <h1>Set your password</h1>

    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <p>
            <label for="email">Email</label>
            <input id="email" name="email" type="email" maxlength="255" required autocomplete="username"
                   value="{{ old('email', $email) }}">
        </p>
        <p>
            <label for="password">New password</label>
            <input id="password" name="password" type="password" minlength="8" required autocomplete="new-password">
        </p>
        <p>
            <label for="password_confirmation">Confirm the password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" required
                   autocomplete="new-password">
        </p>
        <button type="submit">Set password</button>
    </form>
@endsection
