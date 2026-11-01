{{-- GET /admin/organizations. API_Design.md §7.4, P33. --}}
@extends('layouts.admin')

@section('title', 'Organizations')

@section('content')
    <h1>Organizations</h1>

    <p><a href="{{ route('admin.organizations.create') }}">New organization</a></p>

    <div style="overflow-x: auto">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Code</th>
                    <th>Description</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($organizations as $organization)
                    <tr>
                        <td><a href="{{ route('admin.organizations.edit', $organization) }}">{{ $organization->name }}</a></td>
                        <td>{{ $organization->code }}</td>
                        <td>{{ $organization->description ?? '—' }}</td>
                        <td>{{ $organization->is_active ? 'Active' : 'Inactive' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">No organizations yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $organizations->links() }}
@endsection
