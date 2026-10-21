<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\Exceptions\MissingAbilityException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A refresh token only renews the token pair; it cannot call the API.
 */
class RejectRefreshToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if (ApiToken::isRefresh($request->user()?->currentAccessToken())) {
            throw new MissingAbilityException([ApiToken::ACCESS]);
        }

        return $next($request);
    }
}
