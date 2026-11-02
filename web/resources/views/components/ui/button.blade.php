{{--
    A button, or a link styled as one when given an href. web/docs/DESIGN.md §6.
    Links navigate; buttons run an action.

    variant: primary | secondary | danger
--}}
@props(['variant' => 'secondary', 'href' => null, 'type' => 'submit', 'block' => false])

@php
    $classes = \Illuminate\Support\Arr::toCssClasses([
        'button',
        'button-primary' => $variant === 'primary',
        'button-danger' => $variant === 'danger',
        'button-block' => $block,
    ]);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
