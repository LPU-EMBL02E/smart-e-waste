<?php

namespace Database\Factories\Identity;

use App\Modules\Identity\Models\UserQrCredential;
use App\Modules\Identity\Support\QrToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * QR credentials for tests. Database_Schema.md §3.
 *
 * UserFactory already creates one per user, so there is no user default here:
 * pass one with ->for($user) for a user made without the factory.
 *
 * @extends Factory<UserQrCredential>
 */
class UserQrCredentialFactory extends Factory
{
    protected $model = UserQrCredential::class;

    public function definition(): array
    {
        return [
            'token' => QrToken::generate(),
        ];
    }
}
