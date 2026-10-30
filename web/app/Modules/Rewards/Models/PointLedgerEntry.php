<?php

namespace App\Modules\Rewards\Models;

use App\Modules\Deposits\Models\Transaction;
use App\Modules\Identity\Models\User;
use App\Modules\Rewards\Support\LedgerEntryType;
use Database\Factories\Rewards\PointLedgerEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One append-only row in the points ledger. Database_Schema.md §9.
 *
 * Rows are NEVER updated or deleted. There is no balance column anywhere in the
 * database: a balance is always SUM(points_delta), and a correction is a new
 * REVERSAL row (System_Plan.md §5.7 rule 7).
 *
 * Only PointsService writes here. Deposits and Rewards call it inside their own
 * database transaction; neither inserts directly (System_Plan.md §4, boundary
 * rule 1).
 *
 * Has created_at but no updated_at, because a row never changes.
 */
class PointLedgerEntry extends Model
{
    use HasFactory;

    protected $table = 'point_ledger';

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'transaction_id',
        'redemption_id',
        'entry_type',
        'points_delta',
        'description',
    ];

    protected static function newFactory(): PointLedgerEntryFactory
    {
        return PointLedgerEntryFactory::new();
    }

    protected function casts(): array
    {
        return [
            'entry_type' => LedgerEntryType::class,
            'points_delta' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    // ------------------------------------------------------------------ scopes

    /**
     * The rows that count as points EARNED: EARN rows, and the REVERSAL rows
     * that link to a transaction (voids). REDEEM rows and redemption reversals
     * are left out, so redeeming never lowers a leaderboard rank
     * (Database_Schema.md §9, API_Design.md §7.3).
     *
     * The one definition of the rule: PointsService::pointsEarnedBy() and the
     * leaderboards both use it.
     */
    public function scopeCountsAsEarned(Builder $query): Builder
    {
        return $query->whereNotNull('transaction_id')
            ->whereIn('entry_type', [LedgerEntryType::Earn, LedgerEntryType::Reversal]);
    }

    // ---------------------------------------------------------------- relations

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function redemption(): BelongsTo
    {
        return $this->belongsTo(Redemption::class);
    }
}
