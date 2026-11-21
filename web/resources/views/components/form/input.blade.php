{{--
    A labelled input that keeps safe old input and marks itself invalid.
    web/docs/DESIGN.md §6. A password field is never refilled.

    `optional` false is for a field whose rule depends on another field: the hint
    explains it instead of the label.

    bag: a named error bag, for a page with two forms that share a field name.
    The id then defaults to "{bag}_{name}", which the error summary links to.
--}}
@props(['name', 'label', 'id' => null, 'type' => 'text', 'value' => null, 'required' => false, 'optional' => true, 'hint' => null, 'bag' => 'default'])

@php
    $id ??= $bag === 'default' ? $name : "{$bag}_{$name}";
    $invalid = $errors->getBag($bag)->has($name);
    $describedBy = trim(($hint ? "{$id}-hint " : '').($invalid ? "{$id}-error" : ''));
@endphp

<x-form.field :$name :$id :$label :$required :$optional :$hint :$bag>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
           @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
           @required($required)
           @if ($invalid) aria-invalid="true" @endif
           @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
           {{ $attributes->class('field-input') }}>
</x-form.field>
