<?php

namespace App\Modules\Identity\Models;

use App\Modules\Deposits\Models\DepositSession;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Rewards\Models\PointLedgerEntry;
use App\Modules\Rewards\Models\Redemption;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A student or an admin. Database_Schema.md §2.
 *
 * There are only two roles, and admins have no organization_id
 * (System_Plan.md §5.6).
 *
 * `password` is NULL until the student sets one from the emailed link. An
 * account in that state is "not activated" and cannot sign in; no default
 * password is ever assigned or emailed (System_Plan.md §4).
 *
 * There is no points balance column anywhere. A balance is always
 * SUM(point_ledger.points_delta), read through PointsService.
 *
 * Notifiable is for Laravel's password reset mail only. In-app notifications use
 * this project's own `notifications` table through the Notifications module, not
 * Laravel's notification system.
 */
class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    public const ROLE_STUDENT = 'student';

    public const ROLE_ADMIN = 'admin';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'organization_id',
        'role',
        'student_number',
        'email',
        'first_name',
        'last_name',
        'status',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }

    // ---------------------------------------------------------------- relations

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function qrCredential(): HasOne
    {
        return $this->hasOne(UserQrCredential::class);
    }

    public function depositSessions(): HasMany
    {
        return $this->hasMany(DepositSession::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(PointLedgerEntry::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(Redemption::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    // ----------------------------------------------------------------- helpers

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isStudent(): bool
    {
        return $this->role === self::ROLE_STUDENT;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * An account that has never had its password set from the emailed link. The
     * user list shows these so an admin can resend the email
     * (System_Plan.md §4).
     */
    public function isActivated(): bool
    {
        return $this->password !== null;
    }

    public function fullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * What the bin's display shows after a scan. API_Design.md P21.
     */
    public function displayName(): string
    {
        return $this->first_name;
    }
}
