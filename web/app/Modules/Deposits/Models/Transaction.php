<?php

namespace App\Modules\Deposits\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Rewards\Models\PointLedgerEntry;
use App\Modules\Rewards\Models\RewardRule;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A completed deposit and the points it earned. Database_Schema.md §8.
 *
 * Written only by DepositService, inside the same database transaction as the
 * EARN ledger row, and only after verification passed and the actuator confirmed
 * the transfer (System_Plan.md §5.7 rule 4).
 *
 * Voiding never edits points: it sets the status and has PointsService append a
 * REVERSAL row. The unique (transaction_id, entry_type) ledger constraint stops
 * a second reversal.
 *
 * There is no user_id column. The depositing user comes from the session
 * (API_Design.md §5.4, step 5), so queries that need a user join through it.
 */
class Transaction extends Model
{
    use HasFactory;

    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUS_VOIDED = 'VOIDED';

    protected $fillable = [
        'transaction_code',
        'session_id',
        'reward_rule_id',
        'points_awarded',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'points_awarded' => 'integer',
            'voided_at' => 'datetime',
        ];
    }

    // ---------------------------------------------------------------- relations

    public function session(): BelongsTo
    {
        return $this->belongsTo(DepositSession::class, 'session_id');
    }

    public function rewardRule(): BelongsTo
    {
        return $this->belongsTo(RewardRule::class);
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(PointLedgerEntry::class);
    }

    // ----------------------------------------------------------------- helpers

    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOIDED;
    }

    /**
     * Only a COMPLETED transaction can be voided (API_Design.md §7.6).
     */
    public function canBeVoided(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
