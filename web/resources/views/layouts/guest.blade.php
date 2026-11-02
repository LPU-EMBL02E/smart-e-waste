{{--
    Guest shell: sign-in, registration and password pages, and the page asking a
    signed-in user to confirm their email. access-wireframe.html.
--}}
@extends('layouts.base')

@section('body')
    <div class="wrap">
        <header class="site-header">
            <a class="brand" href="{{ route('login') }}">
                <span class="brand-mark" aria-hidden="true">EW</span>
                {{ config('app.name') }}
            </a>
            <span class="small muted">Campus web portal</span>
        </header>

        <div class="guest-layout">
            <div class="intro">
                <p class="intro-title">Your campus.<br>Less e-waste.</p>
                <p class="intro-copy">
                    Keep track of your deposits, points and rewards. One place for your campus collection program.
                </p>
                <div class="portal-notes">
                    <div>
                        <strong>For students</strong>
                        <p>Your profile, deposit records and rewards.</p>
                    </div>
                    <div>
                        <strong>For administrators</strong>
                        <p>Campus collection, records and accounts.</p>
                    </div>
                </div>
            </div>

            <main id="main" class="panel guest-panel" tabindex="-1">
                @include('layouts.partials.messages')

                @yield('content')
            </main>
        </div>
    </div>
@endsection
