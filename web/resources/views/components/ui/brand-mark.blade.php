{{--
    The EcoCircuit logo in the brand badge, beside the app name. Decorative: the
    name next to it is the text. design-system.html #nav.

    The image is 80px, twice the 40px it is shown at, so it stays sharp on
    high-density screens without sending the full-size file.
--}}
<span {{ $attributes->class('brand-mark') }} aria-hidden="true"><img src="{{ asset('images/ecocircuit-logo.png') }}" alt="" width="40" height="40"></span>
