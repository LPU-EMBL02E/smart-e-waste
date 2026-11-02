{{--
    A labelled native select. web/docs/DESIGN.md §6.

    options: value => label. placeholder: the empty first option, if any.
--}}
@props(['name', 'label', 'options', 'id' => null, 'value' => null, 'placeholder' => null, 'required' => false, 'optional' => true, 'hint' => null])

@php
    $id ??= $name;
    $invalid = $errors->has($name);
    $describedBy = trim(($hint ? "{$id}-hint " : '').($invalid ? "{$id}-error" : ''));
    $selected = (string) old($name, $value);
@endphp

<x-form.field :$name :$id :$label :$required :$optional :$hint>
    <select id="{{ $id }}" name="{{ $name }}"
            @required($required)
            @if ($invalid) aria-invalid="true" @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->class('field-input') }}>
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($selected === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
</x-form.field>
