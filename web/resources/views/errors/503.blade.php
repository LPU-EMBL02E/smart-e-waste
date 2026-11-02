@extends('errors.layout')

@section('title', 'Something failed')

@section('content')
    <h1>Something on our side failed</h1>
    <p>Your last action may not have been saved. Try again in a moment.</p>
    <x-ui.button variant="primary" :href="route('root')">Try again</x-ui.button>
@endsection
