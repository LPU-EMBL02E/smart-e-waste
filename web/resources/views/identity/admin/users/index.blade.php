{{-- GET /admin/users, and the CSV import form. API_Design.md §7.4, §7.6, P36. --}}
@extends('layouts.admin')

@section('title', 'Users')

@section('content')
    <h1>Users</h1>

    @if (session('import_errors'))
        <div role="alert">
            <p>Nothing was imported. Fix these rows and upload the file again:</p>
            <ul>
                @foreach (session('import_errors') as $row => $messages)
                    @foreach ($messages as $message)
                        <li>Row {{ $row }}: {{ $message }}</li>
                    @endforeach
                @endforeach
            </ul>
        </div>
    @endif

    <p><a href="{{ route('admin.users.create') }}">New user</a></p>

    <div style="overflow-x: auto">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Student no.</th>
                    <th>Role</th>
                    <th>Organization</th>
                    <th>Status</th>
                    <th>Activated</th>
                    <th>Email confirmed</th>
                    <th>Last login</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>{{ $user->fullName() }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->student_number ?? '—' }}</td>
                        <td>{{ ucfirst($user->role) }}</td>
                        <td>{{ $user->organization?->code ?? '—' }}</td>
                        <td>{{ ucfirst($user->status) }}</td>
                        <td>{{ $user->isActivated() ? 'Yes' : 'Not activated' }}</td>
                        <td>{{ $user->hasVerifiedEmail() ? 'Yes' : 'Not confirmed' }}</td>
                        <td>
                            {{ $user->last_login_at?->timezone(config('ewaste.display_timezone'))->format('d M Y, g:ia') ?? 'Never' }}
                        </td>
                        <td>
                            <a href="{{ route('admin.users.edit', $user) }}">Edit</a>
                            @if ($user->isActive() && ! $user->isActivated())
                                <form method="POST" action="{{ route('admin.users.password-link', $user) }}">
                                    @csrf
                                    <button type="submit">Resend set-password email</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10">No users yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $users->links() }}

    <h2>Import students from CSV</h2>
    <p>
        Columns, with a header row: student_number, first_name, last_name, email, organization_code. Every row becomes a
        student. If any row has a problem, nothing is imported. No emails are sent; send set-password emails from the
        list afterwards.
    </p>

    <form method="POST" action="{{ route('admin.users.import') }}" enctype="multipart/form-data">
        @csrf
        <p>
            <label for="file">CSV file</label>
            <input id="file" name="file" type="file" accept=".csv,text/csv" required>
        </p>
        <button type="submit">Import</button>
    </form>
@endsection
