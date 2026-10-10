<?php

namespace App\Modules\Devices\Models;

use App\Modules\Deposits\Models\DepositSession;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One physical bin: the enclosure and the NodeMCU built into it.
 * Database_Schema.md §4.
 *
 * `secret` uses the `encrypted` cast, so it is encrypted at rest with APP_KEY.
 * A host with a different key cannot read it and every bin must be issued a new
 * secret, which is why APP_KEY travels with the data at handover
 * (System_Plan.md §8).
 *
 * Only a device whose status is `active` is accepted by the device API.
 */
class Device extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_RETIRED = 'retired';

    protected $fillable = [
        'device_code',
        'name',
        'location',
        'status',
        'empty_distance_mm',
        'fill_threshold_percent',
        'weight_offset',
        'weight_scale_factor',
    ];

    protected $hidden = [
        'secret',
    ];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'last_seen_at' => 'datetime',
            'calibrated_at' => 'datetime',
            'empty_distance_mm' => 'decimal:2',
            'fill_threshold_percent' => 'decimal:2',
            'weight_offset' => 'decimal:4',
            'weight_scale_factor' => 'decimal:6',
        ];
    }

    // ---------------------------------------------------------------- relations

    public function telemetry(): HasMany
    {
        return $this->hasMany(BinTelemetry::class);
    }

    /**
     * The newest reading, used for the bin-full check and the dashboard.
     */
    public function latestTelemetry(): HasOne
    {
        return $this->hasOne(BinTelemetry::class)->latestOfMany();
    }

    public function depositSessions(): HasMany
    {
        return $this->hasMany(DepositSession::class);
    }

    // ----------------------------------------------------------------- helpers

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * True once calibration values have been entered on the admin page. Until
     * then GET /device/config returns nulls for all three calibration fields
     * (API_Design.md §5.2).
     */
    public function isCalibrated(): bool
    {
        return $this->weight_offset !== null && $this->weight_scale_factor !== null;
    }
}
