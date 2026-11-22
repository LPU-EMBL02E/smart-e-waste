{{-- GET /register. API_Design.md §7.2, §7.6 ("Register"), P41, P46. --}}
@extends('layouts.guest')

@section('title', 'Create your account')

@section('content')
    <h1>Create your account</h1>
    <p>For students. You'll confirm your email before you can use your account.</p>

    <form method="POST" action="{{ route('register') }}">
        @csrf
        <p>
            <label for="first_name">First name</label>
            <input id="first_name" name="first_name" maxlength="100" required autofocus autocomplete="given-name"
                   value="{{ old('first_name') }}">
        </p>
        <p>
            <label for="last_name">Last name</label>
            <input id="last_name" name="last_name" maxlength="100" required autocomplete="family-name"
                   value="{{ old('last_name') }}">
        </p>
        <p>
            <label for="student_number">Student number</label>
            <input id="student_number" name="student_number" maxlength="50" required
                   value="{{ old('student_number') }}">
        </p>
        <p>
            <label for="email">School email</label>
            <input id="email" name="email" type="email" maxlength="255" required autocomplete="username"
                   value="{{ old('email') }}">
        </p>
        <p>
            <label for="organization_id">Organization</label>
            <select id="organization_id" name="organization_id" required>
                <option value="">Choose your college</option>
                @foreach ($organizations as $organization)
                    <option value="{{ $organization->id }}" @selected(old('organization_id') == $organization->id)>
                        {{ $organization->name }}
                    </option>
                @endforeach
            </select>
        </p>
        <p>
            <label for="password">Password</label>
            <input id="password" name="password" type="password" minlength="8" required autocomplete="new-password">
        </p>
        <p>
            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" required
                   autocomplete="new-password">
        </p>
        <button type="submit">Create account</button>
    </form>

    <p>Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
@endsection
