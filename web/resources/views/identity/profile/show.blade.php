{{--
    GET /profile. API_Design.md §7.3, §7.6 ("Change own details", "Change own
    password"), P32, P48, P50; all-pages-wireframe.html S3.

    The password form uses its own error bag, so a wrong current password shows
    beside the form that was sent.
--}}
@extends('layouts.app')

@section('title', 'Profile')

@section('content')
    <x-ui.page-heading title="Profile" />

    <div class="split">
        <section class="section" id="qr">
            <h2>Your QR code</h2>

            {{-- Server-rendered SVG of a token we generate: letters and digits only. Never recolour or crop it. --}}
            <div class="qr-code" role="img" aria-label="QR code for your account">{!! $qrSvg !!}</div>

            <p class="small muted">
                Hold this code up to the scanner on the bin. It identifies you for one deposit; it can't spend points.
                Last changed @datetime($user->qrCredential->updated_at).
            </p>

            <form method="POST" action="{{ route('profile.qr.regenerate') }}">
                @csrf
                <x-ui.button aria-describedby="qr-regenerate-hint">Make a new QR code</x-ui.button>
            </form>
            <p class="hint" id="qr-regenerate-hint">Your old code stops working at once. Use this if your code was shared or photographed.</p>
        </section>

        <div class="section">
            <section class="section">
                <h2>Your details</h2>
                <form class="form" method="POST" action="{{ route('profile.update') }}">
                    @csrf
                    @method('PUT')
                    <div class="form-row">
                        <x-form.input name="first_name" label="First name" maxlength="100" required autocomplete="given-name"
                                      :value="$user->first_name" />
                        <x-form.input name="last_name" label="Last name" maxlength="100" required autocomplete="family-name"
                                      :value="$user->last_name" />
                    </div>
                    <x-form.input name="email" label="Email" type="email" maxlength="255" required autocomplete="email"
                                  :value="$user->email" hint="A new email has to be confirmed before you can use your account again." />
                    @unless ($user->isAdmin())
                        <x-form.input name="student_number" label="Student number" maxlength="50" required
                                      :value="$user->student_number" />
                        <x-form.select name="organization_id" label="Organization" required
                                       :value="$user->organization_id" :options="$organizations->pluck('name', 'id')"
                                       hint="Your points count towards this organization on the leaderboard." />
                    @endunless
                    <x-form.input name="current_password" label="Current password" type="password" :optional="false"
                                  autocomplete="current-password" hint="Needed only if you change your email." />
                    <x-ui.button variant="primary">Save details</x-ui.button>
                </form>

                <dl class="details-list">
                    <dt>Role</dt>
                    <dd>{{ ucfirst($user->role) }}</dd>
                    <dt>Member since</dt>
                    <dd>@date($user->created_at)</dd>
                </dl>
            </section>

            <section class="section">
                <h2>Organization</h2>
                @if ($user->organization)
                    <dl class="details-list">
                        <dt>Name</dt>
                        <dd>{{ $user->organization->name }}</dd>
                        <dt>Code</dt>
                        <dd>{{ $user->organization->code }}</dd>
                        <dt>About</dt>
                        <dd>{{ $user->organization->description ?? '—' }}</dd>
                    </dl>
                @else
                    <p>No organization (admin account)</p>
                @endif
            </section>

            <section class="section">
                <h2>Change password</h2>
                <form class="form" method="POST" action="{{ route('profile.password.update') }}">
                    @csrf
                    @method('PUT')
                    <x-form.input name="current_password" bag="password" label="Current password" type="password" required
                                  autocomplete="current-password" />
                    <div class="form-row">
                        <x-form.input name="password" bag="password" label="New password" type="password" minlength="8" required
                                      autocomplete="new-password" hint="At least 8 characters." />
                        <x-form.input name="password_confirmation" bag="password" label="Confirm new password" type="password"
                                      minlength="8" required autocomplete="new-password" />
                    </div>
                    <x-ui.button variant="primary" aria-describedby="password-change-hint">Change password</x-ui.button>
                </form>
                <p class="hint" id="password-change-hint">This signs you out everywhere else.</p>
            </section>
        </div>
    </div>
@endsection
