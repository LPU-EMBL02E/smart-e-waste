{{--
    A labelled checkbox for a true or false field. An unticked checkbox sends
    nothing, so the hidden 0 comes first.
--}}
@props(['name', 'label', 'checked' => false, 'hint' => null])

<div class="field">
    <input type="hidden" name="{{ $name }}" value="0">
    <label class="check">
        <input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $checked)) @if ($hint) aria-describedby="{{ $name }}-hint" @endif>
        <span>{{ $label }}</span>
    </label>
    @if ($hint)
        <p class="hint" id="{{ $name }}-hint">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="field-error">{{ $message }}</p>
    @enderror
</div>
