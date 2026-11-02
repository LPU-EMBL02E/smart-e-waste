{{--
    Error pages: one sentence and a way back. all-pages-wireframe.html E1–E5,
    web/docs/DESIGN.md §8. Device routes never reach these; they answer JSON.

    Nothing here reads the session or the database: a 500 may be because either
    of them failed.
--}}
@extends('layouts.base')

@section('body')
    <div class="wrap">
        <header class="site-header">
            <span class="brand">
                <span class="brand-mark" aria-hidden="true">EW</span>
                {{ config('app.name') }}
            </span>
        </header>

        <main id="main" class="panel guest-panel mx-auto my-16" tabindex="-1">
            @yield('content')
        </main>
    </div>
@endsection
