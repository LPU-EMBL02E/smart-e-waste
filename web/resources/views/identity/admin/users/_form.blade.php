{{-- The user fields. API_Design.md §7.6 ("Create or edit a user"). --}}
<p>
    <label for="first_name">First name</label>
    <input id="first_name" name="first_name" maxlength="100" required value="{{ old('first_name', $user->first_name) }}">
</p>
<p>
    <label for="last_name">Last name</label>
    <input id="last_name" name="last_name" maxlength="100" required value="{{ old('last_name', $user->last_name) }}">
</p>
<p>
    <label for="email">Email</label>
    <input id="email" name="email" type="email" maxlength="255" required value="{{ old('email', $user->email) }}">
</p>
<p>
    <label for="student_number">Student number (optional)</label>
    <input id="student_number" name="student_number" maxlength="50"
           value="{{ old('student_number', $user->student_number) }}">
</p>
<p>
    <label for="role">Role</label>
    <select id="role" name="role" required>
        <option value="{{ \App\Modules\Identity\Models\User::ROLE_STUDENT }}"
                @selected(old('role', $user->role) === \App\Modules\Identity\Models\User::ROLE_STUDENT)>Student</option>
        <option value="{{ \App\Modules\Identity\Models\User::ROLE_ADMIN }}"
                @selected(old('role', $user->role) === \App\Modules\Identity\Models\User::ROLE_ADMIN)>Admin</option>
    </select>
</p>
<p>
    <label for="organization_id">Organization</label>
    <select id="organization_id" name="organization_id">
        <option value="">Choose an organization</option>
        @foreach ($organizations as $organization)
            <option value="{{ $organization->id }}"
                    @selected((int) old('organization_id', $user->organization_id) === $organization->id)>
                {{ $organization->name }}
            </option>
        @endforeach
    </select>
    Required for a student. Leave empty for an admin.
</p>
<p>
    <label for="status">Status</label>
    <select id="status" name="status" required>
        <option value="{{ \App\Modules\Identity\Models\User::STATUS_ACTIVE }}"
                @selected(old('status', $user->status) === \App\Modules\Identity\Models\User::STATUS_ACTIVE)>Active</option>
        <option value="{{ \App\Modules\Identity\Models\User::STATUS_INACTIVE }}"
                @selected(old('status', $user->status) === \App\Modules\Identity\Models\User::STATUS_INACTIVE)>Inactive</option>
    </select>
</p>
