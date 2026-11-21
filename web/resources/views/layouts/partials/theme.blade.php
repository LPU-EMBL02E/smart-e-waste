{{--
    Light, dark or the device's setting, for this browser. API_Design.md §7.6
    ("Choose a theme"), P52; web/docs/DESIGN.md §3, "Dark theme".

    Each option is a submit button, so a single click saves it with no script
    (DESIGN.md §10). aria-pressed marks the current one; it is also shown in
    weight and an underline, not by colour alone.
--}}
<form class="theme-switch" method="POST" action="{{ route('theme.update') }}">
    @csrf
    <fieldset>
        <legend>Theme</legend>
        <div class="theme-options">
            @foreach (\App\Modules\Identity\Support\Theme::cases() as $option)
                <button type="submit" name="theme" value="{{ $option->value }}" aria-pressed="{{ $option === $theme ? 'true' : 'false' }}" class="theme-option">{{ $option->label() }}</button>
            @endforeach
        </div>
    </fieldset>
</form>
