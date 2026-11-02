{{-- GET /register. API_Design.md §7.2, §7.6 ("Register"), P41, P46; all-pages-wireframe.html G2. --}}
@extends('layouts.guest')

@section('title', 'Create your account')

@section('content')
    <h1>Create your account</h1>
    <p>For students. You'll confirm your email before you can use your account.</p>

    <form class="form" method="POST" action="{{ route('register') }}">
        @csrf
        <div class="form-row">
            <x-form.input name="first_name" label="First name" maxlength="100" required autofocus autocomplete="given-name" />
            <x-form.input name="last_name" label="Last name" maxlength="100" required autocomplete="family-name" />
        </div>
        <x-form.input name="student_number" label="Student number" maxlength="50" required />
        <x-form.input name="email" label="School email" type="email" maxlength="255" required autocomplete="username" />
        <x-form.select name="organization_id" label="Organization" required placeholder="Choose your college"
                       :options="$organizations->pluck('name', 'id')" />
        <div class="form-row">
            <x-form.input name="password" label="Password" type="password" minlength="8" required autocomplete="new-password"
                          hint="At least 8 characters." />
            <x-form.input name="password_confirmation" label="Confirm password" type="password" minlength="8" required
                          autocomplete="new-password" />
        </div>
        <x-ui.button variant="primary" block>Create account</x-ui.button>
    </form>

    <p>Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
@endsection
