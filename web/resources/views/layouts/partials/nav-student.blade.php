{{-- The student's pages. An admin reaches this shell through "Student view" and gets a way back. --}}
<nav class="nav" aria-label="Student pages">
    <x-ui.nav-link route="home" icon="home">Home</x-ui.nav-link>
    <x-ui.nav-link route="rewards.index" active="rewards.*" icon="rewards">Rewards</x-ui.nav-link>
    <x-ui.nav-link route="redemptions.index" icon="redemptions">My redemptions</x-ui.nav-link>
    <x-ui.nav-link route="transactions.index" icon="history">History</x-ui.nav-link>
    <x-ui.nav-link route="leaderboard" icon="leaderboard">Leaderboard</x-ui.nav-link>
    <x-ui.nav-link route="notifications.index" icon="notifications" :count="$unreadNotifications">Notifications</x-ui.nav-link>
    <x-ui.nav-link route="profile.show" icon="profile">Profile</x-ui.nav-link>
    @if (auth()->user()->isAdmin())
        <x-ui.nav-link route="admin.dashboard" active="admin.*" icon="admin">Admin</x-ui.nav-link>
    @endif
</nav>
