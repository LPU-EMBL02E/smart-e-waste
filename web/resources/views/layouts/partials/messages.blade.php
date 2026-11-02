{{-- Persistent results and an error summary. DESIGN.md §§8–9. --}}
@if (session('status'))
    <div class="feedback success-feedback" role="status">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="feedback error-feedback" role="alert">
        <strong>Please check the following:</strong>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
