{{-- The reward fields. API_Design.md §7.6 ("Create or edit a reward"), P41; all-pages-wireframe.html A19, A20. --}}
<x-form.input name="name" label="Name" maxlength="150" required :value="$reward->name" />
<x-form.textarea name="description" label="Description" :value="$reward->description" />
<div class="form-row">
    <x-form.input name="points_cost" label="Points cost" type="number" step="1" min="1" required :value="$reward->points_cost"
                  :hint="$reward->exists ? 'Changing the price doesn\'t change past redemptions.' : 'Whole number, 1 or more.'" />
    <x-form.input name="stock_quantity" label="Stock" type="number" step="1" min="0" required :value="$reward->stock_quantity"
                  hint="Whole number, 0 or more." />
</div>
<x-form.checkbox name="is_active" label="Active — students can see and redeem it" :checked="$reward->is_active" />
