{{--
    The label, hint and error around one control. web/docs/DESIGN.md §6: a
    persistent label, required or optional stated in text, and the message beside
    the field. The controls tie themselves to the hint and error by these ids.
--}}
@props(['name', 'id', 'label', 'required' => false, 'optional' => true, 'hint' => null])

<div {{ $attributes->class('field') }}>
    <label class="field-label" for="{{ $id }}">
        {{ $label }}
        @if ($required)
            <span>(required)</span>
        @elseif ($optional)
            <span>(optional)</span>
        @endif
    </label>
    {{ $slot }}
    @if ($hint)
        <p class="hint" id="{{ $id }}-hint">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="field-error" id="{{ $id }}-error">{{ $message }}</p>
    @enderror
</div>
