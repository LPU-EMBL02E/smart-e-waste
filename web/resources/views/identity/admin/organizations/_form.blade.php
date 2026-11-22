{{-- The organization fields. API_Design.md §7.6 ("Create or edit an organization"). --}}
<p>
    <label for="name">Name</label>
    <input id="name" name="name" maxlength="150" required value="{{ old('name', $organization->name) }}">
</p>
<p>
    <label for="code">Code</label>
    <input id="code" name="code" maxlength="50" required value="{{ old('code', $organization->code) }}">
    Short and unique, e.g. COE. Used in CSV imports.
</p>
<p>
    <label for="description">Description (optional)</label>
    <textarea id="description" name="description" maxlength="255">{{ old('description', $organization->description) }}</textarea>
</p>
<p>
    {{-- An unticked checkbox sends nothing, so the hidden 0 comes first. --}}
    <input type="hidden" name="is_active" value="0">
    <label>
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $organization->is_active))>
        Active — students can choose it
    </label>
</p>
<p>When inactive, its current students keep their accounts and access. New students can't join it.</p>
