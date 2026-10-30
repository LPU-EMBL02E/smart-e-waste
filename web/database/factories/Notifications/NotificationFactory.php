<?php

namespace Database\Factories\Notifications;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Support\NotificationType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Notifications for tests. Database_Schema.md §12.
 *
 * Test setup only. In the application, the listeners in the Notifications
 * module write these rows in reaction to module events (API_Design.md §7.7).
 *
 * The default is an unread DEPOSIT_COMPLETED notification for a student.
 *
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => NotificationType::DepositCompleted,
            'title' => 'Points added',
            'message' => 'You earned 18 points for deposit TXN-20261030-TEST01.',
        ];
    }

    public function read(): static
    {
        return $this->state(['read_at' => now()]);
    }
}
