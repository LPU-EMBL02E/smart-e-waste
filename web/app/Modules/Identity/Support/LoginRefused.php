<?php

namespace App\Modules\Identity\Support;

use RuntimeException;

/**
 * A sign-in the rules refuse. API_Design.md §7.2, P29.
 *
 * A wrong password, an account with no password and an inactive account all get
 * invalidCredentials(), so the message can't tell anyone which one it was. The
 * controller redirects back with the message.
 */
final class LoginRefused extends RuntimeException
{
    public static function invalidCredentials(): self
    {
        return new self('The email or password is incorrect.');
    }

    public static function tooManyAttempts(int $seconds): self
    {
        return new self("Too many sign-in attempts. Try again in {$seconds} seconds.");
    }
}
