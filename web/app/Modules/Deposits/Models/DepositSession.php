<?php

namespace App\Modules\Deposits\Models;

use App\Modules\Deposits\Support\SessionStatus;
use App\Modules\Devices\Models\Device;
use App\Modules\Identity\Models\User;
use Carbon\CarbonInterface;
use Database\Factories\Deposits\DepositSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One attempt to deposit an item: the guidelines' `ewaste_record`.
 * Database_Schema.md §6.
 *
 * The primary key is a UUID because the device receives it and sends it back on
 * the deposit, complete and cancel calls.
 *
 * It holds the physical evidence for the attempt — weight, verification and
 * actuator result — whether or not it was accepted (API_Design.md P22). A
 * session that completes produces exactly one transaction; the unique
 * `transactions.session_id` is what makes `complete` idempotent.
 *
 * There is no reward_rule_id column here. The rule a session uses is looked up
 * from created_at, and stored on the transaction at completion
 * (API_Design.md §4.1).
 */
class DepositSession extends Model
{
    use HasFactory;
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'device_id',
        'status',
        'expires_at',
    ];

    protected static function newFactory(): DepositSessionFactory
    {
        return DepositSessionFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => SessionStatus::class,
            'expires_at' => 'datetime',
            'closed_at' => 'datetime',
            'net_weight_g' => 'decimal:2',
            'is_weight_stable' => 'boolean',
            'verification_passed' => 'boolean',
            'verification_score' => 'decimal:4',
            'actuator_ok' => 'boolean',
        ];
    }

    // ---------------------------------------------------------------- relations

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class, 'session_id');
    }

    // ------------------------------------------------------------------ scopes

    /**
     * ACCEPTED sessions that have waited longer than the review threshold for
     * their `complete` call: the rule of isAwaitingReview(), in the query. The
     * review page lists them and the dashboard counts them. The status filter
     * uses the (status, expires_at) index.
     */
    public function scopeAwaitingReview(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), SessionStatus::Accepted)
            ->where($query->qualifyColumn('created_at'), '<', self::reviewCutoff());
    }

    // ----------------------------------------------------------------- helpers

    public function hasExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * An ACCEPTED session that has waited longer than the review threshold for
     * its `complete` call. These are listed on /admin/sessions/review and are
     * never expired silently (System_Plan.md §6.3).
     */
    public function isAwaitingReview(): bool
    {
        return $this->status === SessionStatus::Accepted
            && $this->created_at->lt(self::reviewCutoff());
    }

    /** Sessions opened before this have waited past the review threshold. */
    private static function reviewCutoff(): CarbonInterface
    {
        return now()->subMinutes(config('ewaste.review_threshold_minutes'));
    }
}
