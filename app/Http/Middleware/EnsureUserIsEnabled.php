<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs out a web session whose account the admin disabled after it was opened.
 */
class EnsureUserIsEnabled
{
    public const MESSAGE = 'Tu cuenta está deshabilitada.';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->enabled) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // 303 so a form sent with PATCH, PUT or DELETE lands on the login page with GET;
            // this runs outside the Inertia middleware that would otherwise convert the 302.
            return redirect()->route('login', status: 303)->withErrors(['email' => self::MESSAGE]);
        }

        return $next($request);
    }
}
