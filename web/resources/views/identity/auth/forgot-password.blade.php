{{-- GET /forgot-password. API_Design.md §7.2. --}}
@extends('layouts.guest')

@section('title', 'Forgot password')

@section('content')
    <h1>Forgot password</h1>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <p>
            <label for="email">Email</label>
            <input id="email" name="email" type="email" maxlength="255" required autofocus autocomplete="username"
                   value="{{ old('email') }}">
        </p>
        <button type="submit">Send the link</button>
    </form>
@endsection
