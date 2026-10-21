<?php

declare(strict_types=1);

namespace App\Auth;

use Laravel\Sanctum\Contracts\HasAbilities;

/**
 * Abilities of the mobile app tokens: an access token calls the API, a refresh token only renews the pair.
 * Tokens issued before the pair existed carry '*' and keep calling the API.
 */
final class ApiToken
{
    public const ACCESS = 'api';

    public const REFRESH = 'refresh';

    public static function isRefresh(?HasAbilities $token): bool
    {
        return $token !== null && $token->can(self::REFRESH) && $token->cant('*');
    }
}
