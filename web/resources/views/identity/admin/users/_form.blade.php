{{-- The user fields. API_Design.md §7.6 ("Create or edit a user"), all-pages-wireframe.html A5, A6. --}}
@use('App\Modules\Identity\Models\User')

<div class="form-row">
    <x-form.input name="first_name" label="First name" maxlength="100" required :value="$user->first_name" />
    <x-form.input name="last_name" label="Last name" maxlength="100" required :value="$user->last_name" />
</div>
<x-form.input name="email" label="Email" type="email" maxlength="255" required :value="$user->email" />
<x-form.input name="student_number" label="Student number" maxlength="50" :value="$user->student_number" />
<x-form.select name="role" label="Role" required :value="$user->role"
               :options="[User::ROLE_STUDENT => 'Student', User::ROLE_ADMIN => 'Admin']" />
<x-form.select name="organization_id" label="Organization" placeholder="Choose an organization" :optional="false"
               :value="$user->organization_id" :options="$organizations->pluck('name', 'id')"
               hint="Required for a student. Leave empty for an admin." />
<x-form.select name="status" label="Status" required :value="$user->status"
               :options="[User::STATUS_ACTIVE => 'Active', User::STATUS_INACTIVE => 'Inactive']" />
