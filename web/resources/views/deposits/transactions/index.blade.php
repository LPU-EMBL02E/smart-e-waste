{{-- GET /transactions — the signed-in user's own deposit history. API_Design.md §7.3, §7.1. --}}
@extends('layouts.app')

@section('title', 'Transactions')

@section('content')
    <h1>Transactions</h1>

    @include('deposits.transactions._table')

    {{ $transactions->links() }}
@endsection
