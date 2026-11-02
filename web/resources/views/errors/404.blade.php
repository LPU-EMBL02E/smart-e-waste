@extends('errors.layout')

@section('title', 'Page not found')

@section('content')
    <h1>Page not found</h1>
    <p>We couldn't find that page or record.</p>
    <x-ui.button variant="primary" :href="route('root')">Go to your home page</x-ui.button>
@endsection
