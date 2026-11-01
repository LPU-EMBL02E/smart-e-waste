<?php

namespace Database\Factories\Identity;

use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserQrCredential;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * Users for tests. Database_Schema.md §2, web/docs/TRACK-A-ACCOUNTS.md steps A0
 * and A1.
 *
 * The default is an active, confirmed student with a password and an
 * organization. Every user gets a QR credential, as the application gives every
 * account one.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /** Hashed once per test run; hashing is the slow part of making a user. */
    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'role' => User::ROLE_STUDENT,
            'student_number' => fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'status' => User::STATUS_ACTIVE,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            UserQrCredential::factory()->for($user)->create();
        });
    }

    /** Admins have no organization and no student number (System_Plan.md §5.6). */
    public function admin(): static
    {
        return $this->state([
            'organization_id' => null,
            'role' => User::ROLE_ADMIN,
            'student_number' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['status' => User::STATUS_INACTIVE]);
    }

    /** Registered, confirmation link not yet followed (API_Design.md P48). */
    public function unconfirmed(): static
    {
        return $this->state(['email_verified_at' => null]);
    }

    /** Created by an admin, password not yet set from the emailed link. */
    public function unactivated(): static
    {
        return $this->state(['password' => null]);
    }
}
