{{--
    GET /notifications — the signed-in user's own notifications, newest first.
    API_Design.md §7.3, §7.1; all-pages-wireframe.html S9. Admins read their bin
    alerts here too (§7.4), in the admin shell.

    Unread and read differ by a written label and by weight, not only colour
    (web/docs/DESIGN.md §7).
--}}
@extends(auth()->user()->isAdmin() ? 'layouts.admin' : 'layouts.app')

@section('title', 'Notifications')

@section('content')
    <x-ui.page-heading title="Notifications" />

    @if ($notifications->isEmpty())
        <x-ui.empty title="No notifications yet" />
    @else
        <section class="section">
            <div>
                @foreach ($notifications as $notification)
                    <article @class(['notification', 'notification-unread' => ! $notification->isRead()])>
                        <div class="notification-copy">
                            <div class="notification-head">
                                <h2 class="notification-title">{{ $notification->title }}</h2>
                                @if ($notification->isRead())
                                    <x-ui.pill>Read</x-ui.pill>
                                @else
                                    <x-ui.pill tone="warn">Unread</x-ui.pill>
                                @endif
                            </div>
                            <p>{{ $notification->message }}</p>
                            <p class="small muted">@datetime($notification->created_at)</p>
                        </div>

                        @unless ($notification->isRead())
                            <form method="POST" action="{{ route('notifications.read', $notification) }}">
                                @csrf
                                <x-ui.button>Mark as read</x-ui.button>
                            </form>
                        @endunless
                    </article>
                @endforeach
            </div>

            {{ $notifications->links() }}
        </section>
    @endif
@endsection
