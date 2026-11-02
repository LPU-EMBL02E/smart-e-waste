@extends('errors.layout')

@section('title', 'No access')

{{-- A bad or expired email link is also a 403: the signed middleware refuses it. --}}
@section('content')
    @if (request()->routeIs('verification.verify'))
        <h1>This confirmation link doesn't work</h1>
        <p>It may have expired. Send yourself a new one.</p>
        <x-ui.button variant="primary" :href="route('verification.notice')">Send a new link</x-ui.button>
    @else
        <h1>You don't have access to this page</h1>
        <p>{{ request()->is('admin', 'admin/*') ? 'This page is for admins.' : 'Your account can\'t open this page.' }}</p>
        <x-ui.button variant="primary" :href="route('root')">Go to your home page</x-ui.button>
    @endif
@endsection
