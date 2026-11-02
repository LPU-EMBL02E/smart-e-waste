{{-- The organization fields. API_Design.md §7.6 ("Create or edit an organization"). --}}
<p class="form-field">
    <label for="name">Name</label>
    <input class="form-control" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror id="name"
        name="name" maxlength="150" required value="{{ old('name', $organization->name) }}">
    @error('name')
        <span class="field-error" id="name-error">{{ $message }}</span>
    @enderror
</p>
<p class="form-field">
    <label for="code">Code</label>
    <input class="form-control" @error('code') aria-invalid="true" aria-describedby="code-error" @enderror
        id="code" name="code" maxlength="50" required value="{{ old('code', $organization->code) }}">
    Short and unique, e.g. COE. Used in CSV imports.
    @error('code')
        <span class="field-error" id="code-error">{{ $message }}</span>
    @enderror
</p>
<p class="form-field">
    <label for="description">Description (optional)</label>
    <textarea class="form-control" @error('description') aria-invalid="true" aria-describedby="description-error" @enderror
        id="description" name="description" maxlength="255">{{ old('description', $organization->description) }}</textarea>
    @error('description')
        <span class="field-error" id="description-error">{{ $message }}</span>
    @enderror
</p>
<p class="form-field">
    {{-- An unticked checkbox sends nothing, so the hidden 0 comes first. --}}
    <input class="form-control" @error('is_active') aria-invalid="true" aria-describedby="is_active-error" @enderror
        type="hidden" name="is_active" value="0">
    <label>
        <input class="form-control" type="checkbox" name="is_active" value="1" @checked(old('is_active', $organization->is_active))>
        Active — students can choose it
    </label>
    @error('is_active')
        <span class="field-error" id="is_active-error">{{ $message }}</span>
    @enderror
</p>
<p>When inactive, its current students keep their accounts and access. New students can't join it.</p>
