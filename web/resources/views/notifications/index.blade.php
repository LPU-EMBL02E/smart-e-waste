{{--
    GET /notifications — the signed-in user's own notifications, newest first.
    API_Design.md §7.3, §7.1. Admins read their bin alerts here too (§7.4), in
    the admin shell.
--}}
@extends(auth()->user()->isAdmin() ? 'layouts.admin' : 'layouts.app')

@section('title', 'Notifications')

@section('content')
    <h1>Notifications</h1>

    <div class="table-region" role="region" tabindex="0" aria-label="Records">
        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col">Notification</th>
                    <th scope="col">Received</th>
                    <th scope="col">Read</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($notifications as $notification)
                    <tr>
                        <td class="message-cell">
                            <strong>{{ $notification->title }}</strong><br>
                            {{ $notification->message }}
                        </td>
                        <td>{{ $notification->created_at->timezone(config('ewaste.display_timezone'))->format('d M Y, g:ia') }}
                        </td>
                        <td>
                            @if ($notification->isRead())
                                {{ $notification->read_at->timezone(config('ewaste.display_timezone'))->format('d M Y, g:ia') }}
                            @else
                                <form class="app-form" method="POST"
                                    action="{{ route('notifications.read', $notification) }}">
                                    @csrf
                                    <button class="button primary" type="submit">Mark as read</button>
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

    {{ $notifications->links('pagination.portal') }}
@endsection
