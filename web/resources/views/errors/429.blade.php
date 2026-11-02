@extends('errors.layout')

@section('title', 'Too many tries')

@section('content')
    <h1>Too many tries</h1>
    <p>Wait a minute, then try again.</p>
    <x-ui.button :href="url()->previous()">Back</x-ui.button>
@endsection
