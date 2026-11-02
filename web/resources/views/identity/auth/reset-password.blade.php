{{-- GET /reset-password/{token}. Both emails link here. API_Design.md §7.2, P41; all-pages-wireframe.html G4. --}}
@extends('layouts.guest')

@section('title', 'Set your password')

@section('content')
    <h1>Set your password</h1>

    <form class="form" method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-form.input name="email" label="Email" type="email" maxlength="255" required autocomplete="username" :value="$email" />
        <x-form.input name="password" label="New password" type="password" minlength="8" required autocomplete="new-password"
                      hint="At least 8 characters." />
        <x-form.input name="password_confirmation" label="Confirm new password" type="password" minlength="8" required
                      autocomplete="new-password" />
        <x-ui.button variant="primary" block>Save password</x-ui.button>
    </form>

    <a class="text-link" href="{{ route('password.request') }}">Send me a new link</a>
@endsection
