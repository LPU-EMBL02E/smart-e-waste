{{-- GET /reset-password/{token}. Both emails link here. API_Design.md §7.2, P41. --}}
@extends('layouts.guest')

@section('title', 'Set your password')

@section('content')
    <h1>Set your password</h1>

    <form class="app-form" method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <p class="form-field">
            <label for="email">Email</label>
            <input class="form-control" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                id="email" name="email" type="email" maxlength="255" required autocomplete="username"
                value="{{ old('email', $email) }}">
            @error('email')
                <span class="field-error" id="email-error">{{ $message }}</span>
            @enderror
        </p>
        <p class="form-field">
            <label for="password">New password</label>
            <input class="form-control" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                id="password" name="password" type="password" minlength="8" required autocomplete="new-password">
            @error('password')
                <span class="field-error" id="password-error">{{ $message }}</span>
            @enderror
        </p>
        <p class="form-field">
            <label for="password_confirmation">Confirm the password</label>
            <input class="form-control"
                @error('password_confirmation') aria-invalid="true" aria-describedby="password_confirmation-error" @enderror
                id="password_confirmation" name="password_confirmation" type="password" minlength="8" required
                autocomplete="new-password">
            @error('password_confirmation')
                <span class="field-error" id="password_confirmation-error">{{ $message }}</span>
            @enderror
        </p>
        <button class="button primary" type="submit">Set password</button>
    </form>
@endsection
