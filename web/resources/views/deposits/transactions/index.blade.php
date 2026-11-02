{{--
    GET /transactions — the signed-in user's own deposit history.
    API_Design.md §7.3, §7.1; all-pages-wireframe.html S4.
--}}
@extends('layouts.app')

@section('title', 'Deposit history')

@section('content')
    <x-ui.page-heading title="Deposit history" />

    @if ($transactions->isEmpty())
        <x-ui.empty title="No deposits yet">Your deposits will appear here after your first drop-off.</x-ui.empty>
    @else
        <section class="section">
            @include('deposits.transactions._table')
            {{ $transactions->links() }}
        </section>
    @endif
@endsection
