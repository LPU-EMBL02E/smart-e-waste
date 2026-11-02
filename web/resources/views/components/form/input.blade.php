{{--
    A labelled input that keeps safe old input and marks itself invalid.
    web/docs/DESIGN.md §6. A password field is never refilled.

    `optional` false is for a field whose rule depends on another field: the hint
    explains it instead of the label.
--}}
@props(['name', 'label', 'id' => null, 'type' => 'text', 'value' => null, 'required' => false, 'optional' => true, 'hint' => null])

@php
    $id ??= $name;
    $invalid = $errors->has($name);
    $describedBy = trim(($hint ? "{$id}-hint " : '').($invalid ? "{$id}-error" : ''));
@endphp

<x-form.field :$name :$id :$label :$required :$optional :$hint>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
           @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
           @required($required)
           @if ($invalid) aria-invalid="true" @endif
           @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
           {{ $attributes->class('field-input') }}>
</x-form.field>
