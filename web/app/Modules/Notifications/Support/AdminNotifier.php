<?php

namespace App\Modules\Notifications\Support;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Models\Notification;

/**
 * Writes one notification row per admin user. Database_Schema.md §12: "A
 * bin-full or bin-fault alert is one row per admin user."
 *
 * One query for the admin ids and one multi-row insert, however many admins
 * there are, because the listeners run inside the bin's telemetry request
 * (QUEUE_CONNECTION is `sync`).
 */
final class AdminNotifier
{
    public function notify(NotificationType $type, string $title, string $message): void
    {
        $now = now();

        $rows = User::where('role', User::ROLE_ADMIN)
            ->pluck('id')
            ->map(fn (int $userId) => [
                'user_id' => $userId,
                'type' => $type->value,
                'title' => $title,
                'message' => $message,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($rows !== []) {
            Notification::insert($rows);
        }
    }
}
