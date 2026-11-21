{{--
    The flash message and the error summary, shared by every shell. Each stays on
    screen until the next page. web/docs/DESIGN.md §§6, 8.

    A summary item links to its field, except for a refusal that belongs to the
    whole action rather than to one input. A field in a named error bag has the
    id "{bag}_{field}" (components/form/input.blade.php).
--}}
@if (session('status'))
    <div class="toast" role="status">{{ session('status') }}</div>
@endif

@foreach ($errors->getBags() as $bagName => $bag)
    @continue($bag->isEmpty())
    @php($refusals = ['transaction', 'redemption'])
    @php($idPrefix = $bagName === 'default' ? '' : "{$bagName}_")

    <div class="feedback" role="alert">
        @if ($bag->count() === 1 && in_array($bag->keys()[0], $refusals, true))
            {{ $bag->first() }}
        @else
            <strong>Please fix {{ trans_choice('{1} 1 problem|[2,*] :count problems', $bag->count()) }}</strong>
            <ul>
                @foreach ($bag->messages() as $field => $messages)
                    @foreach ($messages as $message)
                        <li>
                            @if (in_array($field, $refusals, true))
                                {{ $message }}
                            @else
                                <a href="#{{ $idPrefix }}{{ $field }}">{{ $message }}</a>
                            @endif
                        </li>
                    @endforeach
                @endforeach
            </ul>
        @endif
    </div>
@endforeach
