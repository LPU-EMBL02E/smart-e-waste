{{-- GET /register. API_Design.md §7.2, §7.6 ("Register"), P41, P46. --}}
@extends('layouts.guest')

@section('title', 'Create your account')

@section('content')
    <h1>Create your account</h1>
    <p>For students. You'll confirm your email before you can use your account.</p>

    <form class="app-form" method="POST" action="{{ route('register') }}">
        @csrf
        <p class="form-field">
            <label for="first_name">First name</label>
            <input class="form-control"
                @error('first_name') aria-invalid="true" aria-describedby="first_name-error" @enderror id="first_name"
                name="first_name" maxlength="100" required autofocus autocomplete="given-name" value="{{ old('first_name') }}">
            @error('first_name')
                <span class="field-error" id="first_name-error">{{ $message }}</span>
            @enderror
        </p>
        <p class="form-field">
            <label for="last_name">Last name</label>
            <input class="form-control" @error('last_name') aria-invalid="true" aria-describedby="last_name-error" @enderror
                id="last_name" name="last_name" maxlength="100" required autocomplete="family-name"
                value="{{ old('last_name') }}">
            @error('last_name')
                <span class="field-error" id="last_name-error">{{ $message }}</span>
            @enderror
        </p>
        <p class="form-field">
            <label for="student_number">Student number</label>
            <input class="form-control"
                @error('student_number') aria-invalid="true" aria-describedby="student_number-error" @enderror
                id="student_number" name="student_number" maxlength="50" required value="{{ old('student_number') }}">
            @error('student_number')
                <span class="field-error" id="student_number-error">{{ $message }}</span>
            @enderror
        </p>
        <p class="form-field">
            <label for="email">School email</label>
            <input class="form-control" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                id="email" name="email" type="email" maxlength="255" required autocomplete="username"
                value="{{ old('email') }}">
            @error('email')
                <span class="field-error" id="email-error">{{ $message }}</span>
            @enderror
        </p>
        <p class="form-field">
            <label for="organization_id">Organization</label>
            <select class="form-control"
                @error('organization_id') aria-invalid="true" aria-describedby="organization_id-error" @enderror
                id="organization_id" name="organization_id" required>
                <option value="">Choose your college</option>
                @foreach ($organizations as $organization)
                    <option value="{{ $organization->id }}" @selected(old('organization_id') == $organization->id)>
                        {{ $organization->name }}
                    </option>
                @endforeach
            </select>
            @error('organization_id')
                <span class="field-error" id="organization_id-error">{{ $message }}</span>
            @enderror
        </p>
        <p class="form-field">
            <label for="password">Password</label>
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
        <button class="button primary" type="submit">Create account</button>
    </form>

    <p>Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
@endsection
