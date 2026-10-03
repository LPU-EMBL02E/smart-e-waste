<?php

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The opaque token a student's QR code encodes. Database_Schema.md §3.
 *
 * One row per user. Regenerating overwrites the row, so the old token stops
 * matching at once and no revocation flag is needed.
 *
 * A scan identifies the student for one deposit and nothing else: it cannot
 * spend points or open the account (System_Plan.md §5.5). The token is random,
 * never the student number, which would be guessable and forgeable.
 */
class UserQrCredential extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token',
    ];

    protected $hidden = [
        'token',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
