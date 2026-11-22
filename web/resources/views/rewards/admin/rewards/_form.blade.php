{{-- The reward fields. API_Design.md §7.6 ("Create or edit a reward"), P41. --}}
<p>
    <label for="name">Name</label>
    <input id="name" name="name" maxlength="150" required value="{{ old('name', $reward->name) }}">
</p>
<p>
    <label for="description">Description</label>
    <textarea id="description" name="description">{{ old('description', $reward->description) }}</textarea>
</p>
<p>
    <label for="points_cost">Cost (points)</label>
    <input id="points_cost" name="points_cost" type="number" step="1" min="1" required
           value="{{ old('points_cost', $reward->points_cost) }}">
</p>
<p>
    <label for="stock_quantity">In stock</label>
    <input id="stock_quantity" name="stock_quantity" type="number" step="1" min="0" required
           value="{{ old('stock_quantity', $reward->stock_quantity) }}">
</p>
<p>
    {{-- An unticked checkbox sends nothing, so the hidden 0 comes first. --}}
    <input type="hidden" name="is_active" value="0">
    <label>
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $reward->is_active))>
        Shown to students
    </label>
</p>
