<?php

namespace App\Modules\Devices\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ultrasonic distance reading from a bin. Database_Schema.md §5.
 *
 * The device sends `distance_mm` only. BinService computes `fill_percent` and
 * `status` before the row is inserted (API_Design.md §5.3), so the firmware
 * never decides whether a bin is full.
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
    ];

    protected function casts(): array
    {
        return [
            'distance_mm' => 'decimal:2',
            'fill_percent' => 'decimal:2',
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
