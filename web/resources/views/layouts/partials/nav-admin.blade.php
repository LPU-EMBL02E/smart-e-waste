{{-- The admin's pages, grouped as in all-pages-wireframe.html. --}}
<nav class="nav" aria-label="Admin pages">
    <p class="nav-group">Overview</p>
    <x-ui.nav-link route="admin.dashboard">Dashboard</x-ui.nav-link>
    <x-ui.nav-link route="notifications.index" :count="$unreadNotifications">Notifications</x-ui.nav-link>

    <p class="nav-group">Bins</p>
    <x-ui.nav-link route="admin.bins.index" active="admin.bins.*">Bins</x-ui.nav-link>
    <x-ui.nav-link route="admin.sessions.review">Session review</x-ui.nav-link>

    <p class="nav-group">Deposits</p>
    <x-ui.nav-link route="admin.transactions.index" active="admin.transactions.*">Transactions</x-ui.nav-link>

    <p class="nav-group">Rewards</p>
    <x-ui.nav-link route="admin.redemptions.index">Redemptions</x-ui.nav-link>
    <x-ui.nav-link route="admin.rewards.index" active="admin.rewards.*">Rewards</x-ui.nav-link>
    <x-ui.nav-link route="admin.points-rate.show">Points rate</x-ui.nav-link>

    <p class="nav-group">People</p>
    <x-ui.nav-link route="admin.users.index" active="admin.users.*">Users</x-ui.nav-link>
    <x-ui.nav-link route="admin.organizations.index" active="admin.organizations.*">Organizations</x-ui.nav-link>

    <p class="nav-group">Reports</p>
    <x-ui.nav-link route="admin.leaderboard.organizations">Organization leaderboard</x-ui.nav-link>
    <x-ui.nav-link route="admin.reports">Reports</x-ui.nav-link>

    <p class="nav-group">My account</p>
    <x-ui.nav-link route="home">Student view</x-ui.nav-link>
</nav>
