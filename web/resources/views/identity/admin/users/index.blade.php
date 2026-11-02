{{-- GET /admin/users, and the CSV import form. API_Design.md §7.4, §7.6, P36; all-pages-wireframe.html A4. --}}
@extends('layouts.admin')

@section('title', 'Users')

@section('content')
    <x-ui.page-heading title="Users">
        <x-slot:actions>
            <x-ui.button variant="primary" :href="route('admin.users.create')">New user</x-ui.button>
        </x-slot:actions>
    </x-ui.page-heading>

    @if (session('import_errors'))
        <div class="feedback" role="alert">
            <strong>Nothing was imported. Fix these rows and upload the file again:</strong>
            <ul>
                @foreach (session('import_errors') as $row => $messages)
                    @foreach ($messages as $message)
                        <li>Row {{ $row }}: {{ $message }}</li>
                    @endforeach
                @endforeach
            </ul>
        </div>
    @endif

    @if ($users->isEmpty())
        <x-ui.empty title="No users yet" />
    @else
        <section class="section">
            <x-ui.table label="Users">
                <x-slot:head>
                    <th scope="col">Name</th>
                    <th scope="col">Email</th>
                    <th scope="col">Student no.</th>
                    <th scope="col">Role</th>
                    <th scope="col">Organization</th>
                    <th scope="col">Status</th>
                    <th scope="col">Activated</th>
                    <th scope="col">Email confirmed</th>
                    <th scope="col">Last login ({{ config('ewaste.display_timezone') }})</th>
                    <th scope="col">Actions</th>
                </x-slot:head>

                @foreach ($users as $user)
                    <tr>
                        <td><a href="{{ route('admin.users.edit', $user) }}">{{ $user->fullName() }}</a></td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->student_number ?? '—' }}</td>
                        <td>{{ ucfirst($user->role) }}</td>
                        <td>{{ $user->organization?->code ?? '—' }}</td>
                        <td><x-ui.pill :status="$user->status" /></td>
                        <td>
                            @if ($user->isActivated())
                                Yes
                            @else
                                <x-ui.pill tone="warn">Not activated</x-ui.pill>
                            @endif
                        </td>
                        <td>
                            @if ($user->hasVerifiedEmail())
                                Yes
                            @else
                                <x-ui.pill tone="warn">Not confirmed</x-ui.pill>
                            @endif
                        </td>
                        <td>@datetime($user->last_login_at, 'Never')</td>
                        <td>
                            @if ($user->isActive() && ! $user->isActivated())
                                <form method="POST" action="{{ route('admin.users.password-link', $user) }}">
                                    @csrf
                                    <x-ui.button>Resend set-password email</x-ui.button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            {{ $users->links() }}
        </section>
    @endif

    <x-ui.panel title="Import students from CSV" class="panel-narrow">
        <div class="section">
            <p class="small muted">
                Columns, with a header row: <code>student_number, first_name, last_name, email, organization_code</code>.
                Every row becomes a student. If any row has a problem, nothing is imported. No emails are sent; send
                set-password emails from the list afterwards.
            </p>

            <form class="form" method="POST" action="{{ route('admin.users.import') }}" enctype="multipart/form-data">
                @csrf
                <x-form.input name="file" label="CSV file" type="file" accept=".csv,text/csv" required />
                <div class="actions">
                    <x-ui.button variant="primary">Import</x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.panel>
@endsection
