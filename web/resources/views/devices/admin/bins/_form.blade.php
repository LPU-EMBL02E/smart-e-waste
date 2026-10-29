{{--
    The register and edit fields. API_Design.md §7.6 ("Register or edit a bin").
    There is no secret field: the server generates it (P39).
--}}
@use('App\Modules\Devices\Models\Device')

<p>
    <label for="device_code">Device code</label>
    <input id="device_code" name="device_code" maxlength="50" required
           value="{{ old('device_code', $device->device_code) }}">
</p>
<p>
    <label for="name">Name</label>
    <input id="name" name="name" maxlength="100" required value="{{ old('name', $device->name) }}">
</p>
<p>
    <label for="location">Location</label>
    <input id="location" name="location" maxlength="255" value="{{ old('location', $device->location) }}">
</p>
<p>
    <label for="empty_distance_mm">Empty distance (mm)</label>
    <input id="empty_distance_mm" name="empty_distance_mm" type="number" step="0.01" min="0.01" required
           value="{{ old('empty_distance_mm', $device->empty_distance_mm) }}">
</p>
<p>
    <label for="fill_threshold_percent">Full at (%)</label>
    <input id="fill_threshold_percent" name="fill_threshold_percent" type="number" step="0.01" min="0" max="100" required
           value="{{ old('fill_threshold_percent', $device->fill_threshold_percent) }}">
</p>
<fieldset>
    <legend>Load cell calibration</legend>
    <p>
        <label for="weight_offset">Weight offset</label>
        <input id="weight_offset" name="weight_offset" type="number" step="0.0001"
               value="{{ old('weight_offset', $device->weight_offset) }}">
    </p>
    <p>
        <label for="weight_scale_factor">Scale factor</label>
        <input id="weight_scale_factor" name="weight_scale_factor" type="number" step="0.000001"
               value="{{ old('weight_scale_factor', $device->weight_scale_factor) }}">
    </p>
</fieldset>
<p>
    <label for="status">Status</label>
    <select id="status" name="status" required>
        @foreach ([Device::STATUS_ACTIVE, Device::STATUS_INACTIVE, Device::STATUS_RETIRED] as $status)
            <option value="{{ $status }}" @selected(old('status', $device->status) === $status)>{{ ucfirst($status) }}</option>
        @endforeach
    </select>
</p>
