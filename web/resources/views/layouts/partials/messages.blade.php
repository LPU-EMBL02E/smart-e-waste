{{--
    The flash message and the error summary, shared by every shell. Each stays on
    screen until the next page. web/docs/DESIGN.md §§6, 8.

    A summary item links to its field, except for a refusal that belongs to the
    whole action rather than to one input.
--}}
@if (session('status'))
    <div class="toast" role="status">{{ session('status') }}</div>
@endif

@if ($errors->any())
    @php($refusals = ['transaction', 'redemption'])

    <div class="feedback" role="alert">
        @if ($errors->count() === 1 && in_array($errors->keys()[0], $refusals, true))
            {{ $errors->first() }}
        @else
            <strong>Please fix {{ trans_choice('{1} 1 problem|[2,*] :count problems', $errors->count()) }}</strong>
            <ul>
                @foreach ($errors->messages() as $field => $messages)
                    @foreach ($messages as $message)
                        <li>
                            @if (in_array($field, $refusals, true))
                                {{ $message }}
                            @else
                                <a href="#{{ $field }}">{{ $message }}</a>
                            @endif
                        </li>
                    @endforeach
                @endforeach
            </ul>
        @endif
    </div>
@endif
