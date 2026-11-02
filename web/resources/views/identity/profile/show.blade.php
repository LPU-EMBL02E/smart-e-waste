{{--
    GET /profile. API_Design.md §7.3, P32, P48; all-pages-wireframe.html S3.

    Details are read-only until §10 question 3 is decided.
--}}
@extends('layouts.app')

@section('title', 'Profile')

@section('content')
    <x-ui.page-heading title="Profile" />

    <div class="split">
        <section class="section">
            <h2>Your QR code</h2>

            {{-- Server-rendered SVG of a token we generate: letters and digits only. Never recolour or crop it. --}}
            <div class="qr-code" role="img" aria-label="QR code for your account">{!! $qrSvg !!}</div>

            <p class="small muted">
                Hold this code up to the scanner on the bin. It identifies you for one deposit; it can't spend points.
                Last changed @datetime($user->qrCredential->updated_at).
            </p>

            <form method="POST" action="{{ route('profile.qr.regenerate') }}">
                @csrf
                <x-ui.button>Make a new QR code</x-ui.button>
            </form>
            <p class="hint">Your old code stops working at once. Use this if your code was shared or photographed.</p>
        </section>

        <div class="section">
            <section class="section">
                <h2>Your details</h2>
                <dl class="details-list">
                    <dt>Name</dt>
                    <dd>{{ $user->fullName() }}</dd>
                    <dt>Email</dt>
                    <dd>{{ $user->email }}</dd>
                    <dt>Student number</dt>
                    <dd>{{ $user->student_number ?? '—' }}</dd>
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

            {{-- Forgot password is a guest page, so a signed-in user has to sign out to reach it. --}}
            <p class="small muted">
                To change your details, contact an admin. To change your password, sign out and use Forgot password.
            </p>
        </div>
    </div>
@endsection
