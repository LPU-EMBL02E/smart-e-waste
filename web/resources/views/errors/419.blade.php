@extends('errors.layout')

@section('title', 'Page expired')

@section('content')
    <h1>This page expired</h1>
    <p>The form was open too long. Go back, reload the page and try again. Nothing was saved.</p>
    <x-ui.button variant="primary" :href="route('login')">Sign in again</x-ui.button>
@endsection
