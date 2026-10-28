<?php

declare(strict_types=1);

namespace App\Accounts;

use App\Accounts\Models\User;

/**
 * Issues the access and refresh tokens of one login session, both named after it.
 */
final class IssueApiTokensAction
{
    /**
     * @return array{token: string, expires_at: string, refresh_token: string, refresh_expires_at: string}
     */
    public function execute(User $user, string $sessionName): array
    {
        $access = $user->createToken(
            $sessionName,
            [ApiToken::ACCESS],
            now()->addMinutes((int) config('sanctum.access_token_ttl')),
        );
        $refresh = $user->createToken(
            $sessionName,
            [ApiToken::REFRESH],
            now()->addMinutes((int) config('sanctum.refresh_token_ttl')),
        );

        return [
            'token' => $access->plainTextToken,
            'expires_at' => $access->accessToken->expires_at->toIso8601String(),
            'refresh_token' => $refresh->plainTextToken,
            'refresh_expires_at' => $refresh->accessToken->expires_at->toIso8601String(),
        ];
    }
}
