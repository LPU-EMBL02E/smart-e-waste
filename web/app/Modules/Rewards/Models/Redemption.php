<?php

namespace App\Modules\Rewards\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A student exchanging points for a reward. Database_Schema.md §11.
 *
 * `points_cost` is the TOTAL charged at redemption time, so a later price change
 * never rewrites history.
 *
 * An admin can cancel one while it is PENDING: the stock is returned and a
 * REVERSAL ledger row gives the points back. A FULFILLED redemption cannot be
 * cancelled (System_Plan.md §6.1).
 */
class Redemption extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_FULFILLED = 'FULFILLED';

    public const STATUS_CANCELLED = 'CANCELLED';

    protected $fillable = [
        'redemption_code',
        'user_id',
        'reward_id',
        'quantity',
        'points_cost',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'points_cost' => 'integer',
            'fulfilled_at' => 'datetime',
        ];
    }

    // ---------------------------------------------------------------- relations

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(PointLedgerEntry::class);
    }

    // ----------------------------------------------------------------- helpers

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /** Fulfil and cancel are both only possible while PENDING. */
    public function canBeFulfilled(): bool
    {
        return $this->isPending();
    }

    public function canBeCancelled(): bool
    {
        return $this->isPending();
    }
}
