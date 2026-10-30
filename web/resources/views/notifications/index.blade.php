{{--
    GET /notifications — the signed-in user's own notifications, newest first.
    API_Design.md §7.3, §7.1. Admins read their bin alerts here too (§7.4), in
    the admin shell.
--}}
@extends(auth()->user()->isAdmin() ? 'layouts.admin' : 'layouts.app')

@section('title', 'Notifications')

@section('content')
    <h1>Notifications</h1>

    <div style="overflow-x: auto">
        <table>
            <thead>
                <tr>
                    <th>Notification</th>
                    <th>Received</th>
                    <th>Read</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($notifications as $notification)
                    <tr>
                        <td>
                            <strong>{{ $notification->title }}</strong><br>
                            {{ $notification->message }}
                        </td>
                        <td>{{ $notification->created_at->timezone(config('ewaste.display_timezone'))->format('d M Y, g:ia') }}</td>
                        <td>
                            @if ($notification->isRead())
                                {{ $notification->read_at->timezone(config('ewaste.display_timezone'))->format('d M Y, g:ia') }}
                            @else
                                <form method="POST" action="{{ route('notifications.read', $notification) }}">
                                    @csrf
                                    <button type="submit">Mark as read</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3">No notifications yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $notifications->links() }}
@endsection
