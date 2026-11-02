{{-- GET /admin/users/{user}/edit. API_Design.md §7.4, §7.6, P41; all-pages-wireframe.html A6. --}}
@extends('layouts.admin')

@section('title', 'Edit '.$user->fullName())

@section('content')
    <x-ui.page-heading :title="$user->fullName()" :back="route('admin.users.index')" back-label="Users" />

    <dl class="details-list">
        <dt>Activated</dt>
        <dd>
            @if ($user->isActivated())
                Yes
            @else
                <x-ui.pill tone="warn">Not activated</x-ui.pill>
            @endif
        </dd>
        <dt>Email confirmed</dt>
        <dd>@datetime($user->email_verified_at, 'Not confirmed')</dd>
        <dt>Last login</dt>
        <dd>@datetime($user->last_login_at, 'Never')</dd>
    </dl>

    <x-ui.panel title="Details" class="panel-narrow">
        <form class="form" method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf
            @method('PUT')
            @include('identity.admin.users._form')
            <div class="actions">
                <x-ui.button variant="primary">Save changes</x-ui.button>
            </div>
        </form>
    </x-ui.panel>

    <x-ui.panel title="Set-password email" class="panel-narrow">
        <div class="section">
            @if ($user->isActive())
                <p>Sends a link to set a password. Any earlier link stops working.</p>
                <form method="POST" action="{{ route('admin.users.password-link', $user) }}">
                    @csrf
                    <x-ui.button>Send set-password email</x-ui.button>
                </form>
            @else
                <p>An inactive account gets no link. Make the account active first.</p>
            @endif
        </div>
    </x-ui.panel>

    <x-ui.panel title="Set a password" class="panel-narrow">
        <form class="form" method="POST" action="{{ route('admin.users.password', $user) }}">
            @csrf
            @method('PUT')
            <p class="small muted">
                Use this only when email can't be sent. Tell the user the password in person; they can change it later
                with Forgot password.
            </p>
            <div class="form-row">
                <x-form.input name="password" label="New password" type="password" minlength="8" required
                              autocomplete="new-password" hint="At least 8 characters." />
                <x-form.input name="password_confirmation" label="Confirm password" type="password" minlength="8" required
                              autocomplete="new-password" />
            </div>
            <div class="actions">
                <x-ui.button>Set password</x-ui.button>
            </div>
        </form>
    </x-ui.panel>
@endsection
