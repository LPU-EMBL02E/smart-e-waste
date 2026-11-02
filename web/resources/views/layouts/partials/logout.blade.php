{{-- Logout is a POST with a CSRF token, never a link (API_Design.md §7.2). --}}
<form class="logout-form" method="POST" action="{{ route('logout') }}">
    @csrf
    <button class="button logout-button" type="submit">
        @include('layouts.partials.icon', ['name' => 'logout'])
        <span>Sign out</span>
    </button>
</form>
