{{-- GET /admin/users/{user}/edit. API_Design.md §7.4, §7.6, P41. --}}
@extends('layouts.admin')

@section('title', 'Edit ' . $user->fullName())

@section('content')
    <h1>{{ $user->fullName() }}</h1>

    <p>Activated: {{ $user->isActivated() ? 'Yes' : 'Not activated' }}</p>
    <p>
        Email confirmed:
        {{ $user->email_verified_at?->timezone(config('ewaste.display_timezone'))->format('d M Y, g:ia') ?? 'Not confirmed' }}
    </p>
    <p>
        Last login:
        {{ $user->last_login_at?->timezone(config('ewaste.display_timezone'))->format('d M Y, g:ia') ?? 'Never' }}
    </p>

    <h2>Details</h2>
    <form class="app-form" method="POST" action="{{ route('admin.users.update', $user) }}">
        @csrf
        @method('PUT')
        @include('identity.admin.users._form')
        <button class="button primary" type="submit">Save changes</button>
    </form>

    <h2>Set-password email</h2>
    @if ($user->isActive())
        <p>Sends a link to set a password. Any earlier link stops working.</p>
        <form class="app-form" method="POST" action="{{ route('admin.users.password-link', $user) }}">
            @csrf
            <button class="button primary" type="submit">Send set-password email</button>
        </form>
    @else
        <p>An inactive account gets no link. Make the account active first.</p>
    @endif

    <h2>Set a password</h2>
    <p>
        Use this only when email can't be sent. Tell the user the password in person; they can change it later with
        Forgot password.
    </p>
    <form class="app-form" method="POST" action="{{ route('admin.users.password', $user) }}">
        @csrf
        @method('PUT')
        <p class="form-field">
            <label for="password">New password</label>
            <input class="form-control" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                id="password" name="password" type="password" minlength="8" required autocomplete="new-password">
            @error('password')
                <span class="field-error" id="password-error">{{ $message }}</span>
            @enderror
        </p>
        <p class="form-field">
            <label for="password_confirmation">Confirm password</label>
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

    <p><a href="{{ route('admin.users.index') }}">Back to users</a></p>
@endsection
