{{-- GET /admin/organizations. API_Design.md §7.4, P33; all-pages-wireframe.html A7. --}}
@extends('layouts.admin')

@section('title', 'Organizations')

@section('content')
    <x-ui.page-heading title="Organizations">
        <x-slot:actions>
            <x-ui.button variant="primary" :href="route('admin.organizations.create')">New organization</x-ui.button>
        </x-slot:actions>
    </x-ui.page-heading>

    @if ($organizations->isEmpty())
        <x-ui.empty title="No organizations yet" />
    @else
        <section class="section">
            <x-ui.table label="Organizations">
                <x-slot:head>
                    <th scope="col">Name</th>
                    <th scope="col">Code</th>
                    <th scope="col">Description</th>
                    <th scope="col">Status</th>
                </x-slot:head>

                @foreach ($organizations as $organization)
                    <tr>
                        <td><a href="{{ route('admin.organizations.edit', $organization) }}">{{ $organization->name }}</a></td>
                        <td>{{ $organization->code }}</td>
                        <td>{{ $organization->description ?? '—' }}</td>
                        <td><x-ui.pill :status="$organization->is_active ? 'Active' : 'Inactive'" /></td>
                    </tr>
                @endforeach
            </x-ui.table>

            {{ $organizations->links() }}
        </section>
    @endif
@endsection
