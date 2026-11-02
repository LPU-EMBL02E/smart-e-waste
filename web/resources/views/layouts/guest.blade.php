{{-- Shared guest presentation. DESIGN.md §§5, 9–10; API_Design.md §7.2. --}}
<!DOCTYPE html>
<html lang="en">

<head>
    @include('layouts.partials.head')
</head>

<body class="ew-app guest-page">
    <a class="skip-link" href="#main-content">Skip to account access</a>
    <div class="guest-wrap">
        <header class="guest-header">
            <a class="brand" href="{{ route('login') }}"><span class="brand-mark" aria-hidden="true">EW</span>Smart
                E-Waste</a>
            @guest
                <nav aria-label="Account navigation">
                    <a href="{{ route('login') }}" @if (request()->routeIs('login')) aria-current="page" @endif>Sign in</a>
                    <a href="{{ route('register') }}"
                        @if (request()->routeIs('register')) aria-current="page" @endif>Register</a>
                    <a href="{{ route('password.request') }}"
                        @if (request()->routeIs('password.request')) aria-current="page" @endif>Forgot password</a>
                </nav>
            @endguest
        </header>
        <main class="guest-layout" id="main-content" tabindex="-1">
            <aside class="guest-intro" aria-label="About the campus portal">
                <p class="intro-title">Your campus.<br>Less e-waste.</p>
                <p>Keep track of your deposits, points and rewards. One place for your campus collection program.</p>
                <div class="portal-notes">
                    <div>
                        <strong>For students</strong>
                        <p>Your profile, disposal records and rewards.</p>
                    </div>
                    <div>
                        <strong>For administrators</strong>
                        <p>Campus collection, records and accounts.</p>
                    </div>
                </div>
            </aside>
            <section class="guest-panel page-content" aria-label="Account access">
                @include('layouts.partials.messages')
                @yield('content')
                @guest
                    @if (request()->routeIs('login'))
                        <div class="account-help">
                            <a href="{{ route('password.request') }}">Forgot password?</a>
                            <p>New to the portal? <a href="{{ route('register') }}">Create your student account</a> and
                                confirm your email to get started.</p>
                        </div>
                    @elseif (!request()->routeIs('register'))
                        <p><a href="{{ route('login') }}">Back to sign in</a></p>
                    @endif
                @endguest
            </section>
        </main>
        <footer class="guest-footer">Campus web portal · Smart E-Waste</footer>
    </div>
</body>

</html>
