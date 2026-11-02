{{-- The reward fields. API_Design.md §7.6 ("Create or edit a reward"), P41. --}}
<p class="form-field">
    <label for="name">Name</label>
    <input class="form-control" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror id="name"
        name="name" maxlength="150" required value="{{ old('name', $reward->name) }}">
    @error('name')
        <span class="field-error" id="name-error">{{ $message }}</span>
    @enderror
</p>
<p class="form-field">
    <label for="description">Description</label>
    <textarea class="form-control" @error('description') aria-invalid="true" aria-describedby="description-error" @enderror
        id="description" name="description">{{ old('description', $reward->description) }}</textarea>
    @error('description')
        <span class="field-error" id="description-error">{{ $message }}</span>
    @enderror
</p>
<p class="form-field">
    <label for="points_cost">Cost (points)</label>
    <input class="form-control" @error('points_cost') aria-invalid="true" aria-describedby="points_cost-error" @enderror
        id="points_cost" name="points_cost" type="number" step="1" min="1" required
        value="{{ old('points_cost', $reward->points_cost) }}">
    @error('points_cost')
        <span class="field-error" id="points_cost-error">{{ $message }}</span>
    @enderror
</p>
<p class="form-field">
    <label for="stock_quantity">In stock</label>
    <input class="form-control"
        @error('stock_quantity') aria-invalid="true" aria-describedby="stock_quantity-error" @enderror
        id="stock_quantity" name="stock_quantity" type="number" step="1" min="0" required
        value="{{ old('stock_quantity', $reward->stock_quantity) }}">
    @error('stock_quantity')
        <span class="field-error" id="stock_quantity-error">{{ $message }}</span>
    @enderror
</p>
<p class="form-field">
    {{-- An unticked checkbox sends nothing, so the hidden 0 comes first. --}}
    <input class="form-control" @error('is_active') aria-invalid="true" aria-describedby="is_active-error" @enderror
        type="hidden" name="is_active" value="0">
    <label>
        <input class="form-control" type="checkbox" name="is_active" value="1" @checked(old('is_active', $reward->is_active))>
        Shown to students
    </label>
    @error('is_active')
        <span class="field-error" id="is_active-error">{{ $message }}</span>
    @enderror
</p>
