<?php

namespace App\Modules\Devices\Models;

use Database\Factories\Devices\BinTelemetryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ultrasonic distance reading from a bin, with the service-panel state and
 * the bin's current fault. Database_Schema.md §5.
 *
 * The device sends `distance_mm`, `panel_open` and `fault_code`. BinService
 * computes `fill_percent` and `status` before the row is inserted
 * (API_Design.md §5.3), so the firmware never decides whether a bin is full.
 * The panel state and the fault are the device's to decide; the server only
 * stores them.
 *
 * Has created_at but no updated_at: a reading is written once and never changed.
 */
class BinTelemetry extends Model
{
    use HasFactory;

    protected $table = 'bin_telemetry';

    public const UPDATED_AT = null;

    public const STATUS_OK = 'OK';

    public const STATUS_FULL = 'FULL';

    protected $fillable = [
        'device_id',
        'distance_mm',
        'fill_percent',
        'status',
        'panel_open',
        'fault_code',
    ];

    protected static function newFactory(): BinTelemetryFactory
    {
        return BinTelemetryFactory::new();
    }

    protected function casts(): array
    {
        return [
            'distance_mm' => 'decimal:2',
            'fill_percent' => 'decimal:2',
            'panel_open' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function isFull(): bool
    {
        return $this->status === self::STATUS_FULL;
    }
}
