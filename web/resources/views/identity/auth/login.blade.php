{{-- GET /login. API_Design.md §7.2, all-pages-wireframe.html G1. --}}
@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
    <h1>Sign in</h1>
    <p>Use the email linked to your campus account.</p>

    <form class="form" method="POST" action="{{ route('login') }}">
        @csrf
        <x-form.input name="email" label="Email" type="email" maxlength="255" required autofocus autocomplete="username" />
        <x-form.input name="password" label="Password" type="password" required autocomplete="current-password" />
        <x-ui.button variant="primary" block>Sign in</x-ui.button>
    </form>

    <a class="text-link" href="{{ route('password.request') }}">Forgot password?</a>

    <div class="account-help">
        <h2 class="text-body">Need an account?</h2>
        <p>Create a student account. You'll confirm your email before you can use it.</p>
        <a class="text-link" href="{{ route('register') }}">Create account</a>
    </div>
@endsection
