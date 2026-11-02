{{-- The user fields. API_Design.md §7.6 ("Create or edit a user"). --}}
<p class="form-field">
    <label for="first_name">First name</label>
    <input class="form-control" @error('first_name') aria-invalid="true" aria-describedby="first_name-error" @enderror
        id="first_name" name="first_name" maxlength="100" required value="{{ old('first_name', $user->first_name) }}">
    @error('first_name')
        <span class="field-error" id="first_name-error">{{ $message }}</span>
    @enderror
</p>
<p class="form-field">
    <label for="last_name">Last name</label>
    <input class="form-control" @error('last_name') aria-invalid="true" aria-describedby="last_name-error" @enderror
        id="last_name" name="last_name" maxlength="100" required value="{{ old('last_name', $user->last_name) }}">
    @error('last_name')
        <span class="field-error" id="last_name-error">{{ $message }}</span>
    @enderror
</p>
<p class="form-field">
    <label for="email">Email</label>
    <input class="form-control" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
        id="email" name="email" type="email" maxlength="255" required value="{{ old('email', $user->email) }}">
    @error('email')
        <span class="field-error" id="email-error">{{ $message }}</span>
    @enderror
</p>
<p class="form-field">
    <label for="student_number">Student number (optional)</label>
    <input class="form-control"
        @error('student_number') aria-invalid="true" aria-describedby="student_number-error" @enderror
        id="student_number" name="student_number" maxlength="50"
        value="{{ old('student_number', $user->student_number) }}">
    @error('student_number')
        <span class="field-error" id="student_number-error">{{ $message }}</span>
    @enderror
</p>
<p class="form-field">
    <label for="role">Role</label>
    <select class="form-control" @error('role') aria-invalid="true" aria-describedby="role-error" @enderror
        id="role" name="role" required>
        <option value="{{ \App\Modules\Identity\Models\User::ROLE_STUDENT }}" @selected(old('role', $user->role) === \App\Modules\Identity\Models\User::ROLE_STUDENT)>Student
        </option>
        <option value="{{ \App\Modules\Identity\Models\User::ROLE_ADMIN }}" @selected(old('role', $user->role) === \App\Modules\Identity\Models\User::ROLE_ADMIN)>Admin</option>
    </select>
    @error('role')
        <span class="field-error" id="role-error">{{ $message }}</span>
    @enderror
</p>
<p class="form-field">
    <label for="organization_id">Organization</label>
    <select class="form-control"
        @error('organization_id') aria-invalid="true" aria-describedby="organization_id-error" @enderror
        id="organization_id" name="organization_id">
        <option value="">Choose an organization</option>
        @foreach ($organizations as $organization)
            <option value="{{ $organization->id }}" @selected((int) old('organization_id', $user->organization_id) === $organization->id)>
                {{ $organization->name }}
            </option>
        @endforeach
    </select>
    Required for a student. Leave empty for an admin.
    @error('organization_id')
        <span class="field-error" id="organization_id-error">{{ $message }}</span>
    @enderror
</p>
<p class="form-field">
    <label for="status">Status</label>
    <select class="form-control" @error('status') aria-invalid="true" aria-describedby="status-error" @enderror
        id="status" name="status" required>
        <option value="{{ \App\Modules\Identity\Models\User::STATUS_ACTIVE }}" @selected(old('status', $user->status) === \App\Modules\Identity\Models\User::STATUS_ACTIVE)>Active
        </option>
        <option value="{{ \App\Modules\Identity\Models\User::STATUS_INACTIVE }}" @selected(old('status', $user->status) === \App\Modules\Identity\Models\User::STATUS_INACTIVE)>Inactive
        </option>
    </select>
    @error('status')
        <span class="field-error" id="status-error">{{ $message }}</span>
    @enderror
</p>
