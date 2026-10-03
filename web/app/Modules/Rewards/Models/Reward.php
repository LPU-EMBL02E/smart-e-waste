<?php

namespace App\Modules\Rewards\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Something a student can redeem points for. Database_Schema.md §10.
 *
 * Switched off with is_active, never deleted (API_Design.md P33). Changing
 * points_cost does not affect past redemptions: each one stores the cost it was
 * redeemed at.
 *
 * Stock is never decremented through this model. RedemptionService uses a
 * guarded UPDATE so it can never go negative (System_Plan.md §6.1).
 */
class Reward extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'points_cost',
        'stock_quantity',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'points_cost' => 'integer',
            'stock_quantity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(Redemption::class);
    }

    /**
     * What the student-facing catalogue shows (API_Design.md §7.3).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isInStock(int $quantity = 1): bool
    {
        return $this->stock_quantity >= $quantity;
    }
}
