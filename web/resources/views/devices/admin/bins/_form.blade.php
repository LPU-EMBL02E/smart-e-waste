{{--
    The register and edit fields. API_Design.md §7.6 ("Register or edit a bin"),
    all-pages-wireframe.html A14, A16. There is no secret field: the server
    generates it (P39).
--}}
@use('App\Modules\Devices\Models\Device')

<div class="form-row">
    <x-form.input name="device_code" label="Device code" maxlength="50" required :value="$device->device_code"
                  hint="Unique. The bin sends it with every request." />
    <x-form.input name="name" label="Name" maxlength="100" required :value="$device->name" />
</div>
<x-form.input name="location" label="Location" maxlength="255" :value="$device->location" />
<div class="form-row">
    <x-form.input name="empty_distance_mm" label="Empty distance (mm)" type="number" step="0.01" min="0.01" required
                  :value="$device->empty_distance_mm" hint="Ultrasonic reading with the bin empty. Above 0." />
    <x-form.input name="fill_threshold_percent" label="Fill threshold (%)" type="number" step="0.01" min="0" max="100" required
                  :value="$device->fill_threshold_percent" hint="0–100. The bin counts as full at or above this." />
</div>

<div class="form-group">
    <h2>Load cell calibration</h2>
    @if ($device->calibrated_at)
        <p class="hint">
            Last calibrated @date($device->calibrated_at). Saving new values updates this date. The bin uses them after
            its next config fetch.
        </p>
    @endif
    <div class="form-row">
        <x-form.input name="weight_offset" label="Offset" type="number" step="0.0001" :value="$device->weight_offset" />
        <x-form.input name="weight_scale_factor" label="Scale factor" type="number" step="0.000001"
                      :value="$device->weight_scale_factor" />
    </div>
</div>

<x-form.select name="status" label="Status" required :value="$device->status"
               hint="Inactive or retired bins can't take deposits. Retired bins leave the dashboard."
               :options="[
                   Device::STATUS_ACTIVE => 'Active',
                   Device::STATUS_INACTIVE => 'Inactive',
                   Device::STATUS_RETIRED => 'Retired',
               ]" />
