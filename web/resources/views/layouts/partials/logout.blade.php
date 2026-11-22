{{-- Logout is a POST with a CSRF token, never a link (API_Design.md §7.2). --}}
<form method="POST" action="{{ route('logout') }}">
    @csrf
    <button type="submit">Sign out</button>
</form>
