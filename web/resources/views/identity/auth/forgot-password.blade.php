{{-- GET /forgot-password. API_Design.md §7.2, all-pages-wireframe.html G3. --}}
@extends('layouts.guest')

@section('title', 'Forgot password')

@section('content')
    <h1>Forgot your password?</h1>
    <p>Enter your email and we'll send you a link to set a new password.</p>

    <form class="form" method="POST" action="{{ route('password.email') }}">
        @csrf
        <x-form.input name="email" label="Email" type="email" maxlength="255" required autofocus autocomplete="username" />
        <x-ui.button variant="primary" block>Email me a link</x-ui.button>
    </form>

    <a class="text-link" href="{{ route('login') }}">Back to sign in</a>
@endsection
