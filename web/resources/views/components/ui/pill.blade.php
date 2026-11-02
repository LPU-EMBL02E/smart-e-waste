{{--
    A status badge: a written state, with colour only supporting it.
    design-system.html #status.

    Give a documented state as `status`, or a `tone` and the label as the slot.
    tone: ok | warn | danger | null (neutral)
--}}
@use('App\Support\Display')

@props(['status' => null, 'tone' => null])

@php
    $tone = $status === null ? $tone : Display::statusTone($status);
@endphp

<span {{ $attributes->class(['pill', "pill-{$tone}" => $tone]) }}>{{ $status === null ? $slot : Display::statusLabel($status) }}</span>
