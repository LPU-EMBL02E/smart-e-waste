{{-- The organization fields. API_Design.md §7.6 ("Create or edit an organization"), all-pages-wireframe.html A8, A9. --}}
<x-form.input name="name" label="Name" maxlength="150" required :value="$organization->name" hint="Up to 150 characters." />
<x-form.input name="code" label="Code" maxlength="50" required :value="$organization->code"
              hint="Short and unique, e.g. COE. Used in CSV imports." />
<x-form.textarea name="description" label="Description" maxlength="255" :value="$organization->description" />
<x-form.checkbox name="is_active" label="Active — students can choose it" :checked="$organization->is_active"
                 hint="When inactive, its current students keep their accounts and access. New students can't join it." />
