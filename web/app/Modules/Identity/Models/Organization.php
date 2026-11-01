<?php

namespace App\Modules\Identity\Models;

use Database\Factories\Identity\OrganizationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An organization or college that students belong to.
 *
 * Database_Schema.md §1. Switched off with is_active, never deleted
 * (API_Design.md P33).
 */
class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected static function newFactory(): OrganizationFactory
    {
        return OrganizationFactory::new();
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Those that take new students (web/docs/ACCOUNT-MANAGEMENT.md).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Students only. The organization leaderboard counts students, never admins,
     * so this scope is what the Reporting queries group on
     * (System_Plan.md §5.6).
     */
    public function students(): HasMany
    {
        return $this->hasMany(User::class)->where('role', User::ROLE_STUDENT);
    }
}
