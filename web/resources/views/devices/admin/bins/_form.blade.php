{{--
    The register and edit fields. API_Design.md §7.6 ("Register or edit a bin").
    There is no secret field: the server generates it (P39).
--}}
@use('App\Modules\Devices\Models\Device')

<p class="form-field">
    <label for="device_code">Device code</label>
    <input class="form-control" @error('device_code') aria-invalid="true" aria-describedby="device_code-error" @enderror
        id="device_code" name="device_code" maxlength="50" required value="{{ old('device_code', $device->device_code) }}">
    @error('device_code')
        <span class="field-error" id="device_code-error">{{ $message }}</span>
    @enderror

</p>
<p class="form-field">
    <label for="name">Name</label>
    <input class="form-control" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror
        id="name" name="name" maxlength="100" required value="{{ old('name', $device->name) }}">
    @error('name')
        <span class="field-error" id="name-error">{{ $message }}</span>
    @enderror
</p>
<p class="form-field">
    <label for="location">Location</label>
    <input class="form-control" @error('location') aria-invalid="true" aria-describedby="location-error" @enderror
        id="location" name="location" maxlength="255" value="{{ old('location', $device->location) }}">
    @error('location')
        <span class="field-error" id="location-error">{{ $message }}</span>
    @enderror
</p>
<p class="form-field">
    <label for="empty_distance_mm">Empty distance (mm)</label>
    <input class="form-control"
        @error('empty_distance_mm') aria-invalid="true" aria-describedby="empty_distance_mm-error" @enderror
        id="empty_distance_mm" name="empty_distance_mm" type="number" step="0.01" min="0.01" required
        value="{{ old('empty_distance_mm', $device->empty_distance_mm) }}">
    @error('empty_distance_mm')
        <span class="field-error" id="empty_distance_mm-error">{{ $message }}</span>
    @enderror
</p>
<p class="form-field">
    <label for="fill_threshold_percent">Full at (%)</label>
    <input class="form-control"
        @error('fill_threshold_percent') aria-invalid="true" aria-describedby="fill_threshold_percent-error" @enderror
        id="fill_threshold_percent" name="fill_threshold_percent" type="number" step="0.01" min="0"
        max="100" required value="{{ old('fill_threshold_percent', $device->fill_threshold_percent) }}">
    @error('fill_threshold_percent')
        <span class="field-error" id="fill_threshold_percent-error">{{ $message }}</span>
    @enderror
</p>
<fieldset>
    <legend>Load cell calibration</legend>
    <p class="form-field">
        <label for="weight_offset">Weight offset</label>
        <input class="form-control"
            @error('weight_offset') aria-invalid="true" aria-describedby="weight_offset-error" @enderror
            id="weight_offset" name="weight_offset" type="number" step="0.0001"
            value="{{ old('weight_offset', $device->weight_offset) }}">
        @error('weight_offset')
            <span class="field-error" id="weight_offset-error">{{ $message }}</span>
        @enderror
    </p>
    <p class="form-field">
        <label for="weight_scale_factor">Scale factor</label>
        <input class="form-control"
            @error('weight_scale_factor') aria-invalid="true" aria-describedby="weight_scale_factor-error" @enderror
            id="weight_scale_factor" name="weight_scale_factor" type="number" step="0.000001"
            value="{{ old('weight_scale_factor', $device->weight_scale_factor) }}">
        @error('weight_scale_factor')
            <span class="field-error" id="weight_scale_factor-error">{{ $message }}</span>
        @enderror
    </p>
</fieldset>
<p class="form-field">
    <label for="status">Status</label>
    <select class="form-control" @error('status') aria-invalid="true" aria-describedby="status-error" @enderror
        id="status" name="status" required>
        @foreach ([Device::STATUS_ACTIVE, Device::STATUS_INACTIVE, Device::STATUS_RETIRED] as $status)
            <option value="{{ $status }}" @selected(old('status', $device->status) === $status)>{{ ucfirst($status) }}</option>
        @endforeach
    </select>
    @error('status')
        <span class="field-error" id="status-error">{{ $message }}</span>
    @enderror
</p>
