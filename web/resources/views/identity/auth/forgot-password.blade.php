{{-- GET /forgot-password. API_Design.md §7.2. --}}
@extends('layouts.guest')

@section('title', 'Forgot password')

@section('content')
    <h1>Forgot password</h1>

    <form class="app-form" method="POST" action="{{ route('password.email') }}">
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
        <button class="button primary" type="submit">Send the link</button>
    </form>
@endsection
