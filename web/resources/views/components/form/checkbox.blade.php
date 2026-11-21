{{--
    A labelled checkbox for a true or false field. An unticked checkbox sends
    nothing, so the hidden 0 comes first. The id is the field name, so the error
    summary's link lands on it (layouts/partials/messages).
--}}
@props(['name', 'label', 'checked' => false, 'hint' => null])

@php
    $invalid = $errors->has($name);
    $describedBy = trim(($hint ? "{$name}-hint " : '').($invalid ? "{$name}-error" : ''));
@endphp

<div class="field">
    <input type="hidden" name="{{ $name }}" value="0">
    <label class="check">
        <input type="checkbox" id="{{ $name }}" name="{{ $name }}" value="1" @checked(old($name, $checked))
               @if ($invalid) aria-invalid="true" @endif
               @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif>
        <span>{{ $label }}</span>
    </label>
    @if ($hint)
        <p class="hint" id="{{ $name }}-hint">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="field-error" id="{{ $name }}-error">{{ $message }}</p>
    @enderror
</div>
