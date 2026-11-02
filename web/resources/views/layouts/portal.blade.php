{{--
    The signed-in shell, shared by students and admins: the sidebar and the main
    column. design-system.html #nav. `layouts.app` and `layouts.admin` choose
    what goes in the sidebar.
--}}
@extends('layouts.base')

@section('body')
    <div class="app">
        @include('layouts.partials.sidebar')

        <main id="main" class="content" tabindex="-1">
            @include('layouts.partials.messages')

            @yield('content')
        </main>
    </div>
@endsection
