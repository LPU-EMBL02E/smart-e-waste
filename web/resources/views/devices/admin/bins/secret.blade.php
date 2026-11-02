{{--
    The bin's new secret, shown once after registration or a new-secret request.
    API_Design.md P39, all-pages-wireframe.html A17. It is encrypted at rest and
    never displayed again.
--}}
@extends('layouts.admin')

@section('title', 'Secret for '.$device->name)

@section('content')
    <x-ui.page-heading title="Copy the bin's secret now" />

    <section class="section panel-narrow">
        <p>
            <strong>This is the only time it is shown.</strong>
            Enter it in the bin's setup portal together with the device code.
        </p>

        <dl class="details-list">
            <dt>Device code</dt>
            <dd><code>{{ $device->device_code }}</code></dd>
        </dl>

        <div class="secret-value" aria-label="Device secret">{{ $secret }}</div>

        <p class="hint">If you lose it, issue a new secret from the bin's page. The old one stops working.</p>

        <div class="actions">
            <x-ui.button variant="primary" :href="route('admin.bins.show', $device)">Done — go to the bin</x-ui.button>
        </div>
    </section>
@endsection
