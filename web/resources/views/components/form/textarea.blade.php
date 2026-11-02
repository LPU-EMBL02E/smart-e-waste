{{-- A labelled text area. web/docs/DESIGN.md §6. --}}
@props(['name', 'label', 'id' => null, 'value' => null, 'required' => false, 'optional' => true, 'hint' => null])

@php
    $id ??= $name;
    $invalid = $errors->has($name);
    $describedBy = trim(($hint ? "{$id}-hint " : '').($invalid ? "{$id}-error" : ''));
@endphp

<x-form.field :$name :$id :$label :$required :$optional :$hint>
    <textarea id="{{ $id }}" name="{{ $name }}" rows="3"
              @required($required)
              @if ($invalid) aria-invalid="true" @endif
              @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
              {{ $attributes->class('field-input') }}>{{ old($name, $value) }}</textarea>
</x-form.field>
