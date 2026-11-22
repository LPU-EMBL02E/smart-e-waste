{{--
    The bin's new secret, shown once after registration or a new-secret request.
    API_Design.md P39. It is encrypted at rest and never displayed again.
--}}
@extends('layouts.admin')

@section('title', 'Secret for '.$device->name)

@section('content')
    <h1>Secret for {{ $device->name }}</h1>

    <p>Enter these in the bin's setup portal now. The secret is shown only on this page.
        If it is lost, issue a new one; the old one stops working at once.</p>

    <dl>
        <dt>Device code</dt>
        <dd><code>{{ $device->device_code }}</code></dd>
        <dt>Secret</dt>
        <dd><code>{{ $secret }}</code></dd>
    </dl>

    <p><a href="{{ route('admin.bins.show', $device) }}">Done</a></p>
@endsection
