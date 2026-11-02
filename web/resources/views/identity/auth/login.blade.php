{{-- GET /login. API_Design.md §7.2. --}}
@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
    <h1>Sign in</h1>

    <form class="app-form" method="POST" action="{{ route('login') }}">
        @csrf
        <p class="form-field">
            <label for="email">Email</label>
            <input class="form-control" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                id="email" name="email" type="email" maxlength="255" required autofocus autocomplete="username"
                value="{{ old('email') }}">
            @error('email')
                <span class="field-error" id="email-error">{{ $message }}</span>
            @enderror
        </p>
        <p class="form-field">
            <label for="password">Password</label>
            <input class="form-control" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                id="password" name="password" type="password" required autocomplete="current-password">
            @error('password')
                <span class="field-error" id="password-error">{{ $message }}</span>
            @enderror
        </p>
        <button class="button primary" type="submit">Sign in</button>
    </form>
@endsection
