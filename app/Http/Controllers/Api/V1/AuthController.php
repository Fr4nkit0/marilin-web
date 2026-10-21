<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\IssueApiTokensAction;
use App\Auth\ApiToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Exceptions\MissingAbilityException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function login(LoginRequest $request, IssueApiTokensAction $issueTokens): JsonResponse
    {
        /** @var User|null $user */
        $user = User::query()
            ->with('driver')
            ->where('email', $request->string('email')->toString())
            ->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if (! $user->enabled) {
            throw ValidationException::withMessages([
                'email' => ['User account is disabled.'],
            ]);
        }

        if (! $user->driver || ! $user->driver->enabled) {
            throw ValidationException::withMessages([
                'email' => ['User is not an active driver.'],
            ]);
        }

        $deviceName = $request->filled('device_name')
            ? $request->string('device_name')->toString()
            : 'mobile-driver';

        return response()->json([
            'data' => [
                ...$issueTokens->execute($user, $deviceName),
                'user' => new UserResource($user),
            ],
            'message' => 'Login successful',
        ]);
    }

    /**
     * Swaps a refresh token for a new pair, revoking the used one and its access token.
     */
    public function refresh(Request $request, IssueApiTokensAction $issueTokens): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->load('driver');
        $refreshToken = $user->currentAccessToken();

        if (! ApiToken::isRefresh($refreshToken)) {
            throw new MissingAbilityException([ApiToken::REFRESH]);
        }

        if (! $user->enabled || ! $user->driver || ! $user->driver->enabled) {
            $user->tokens()->delete();

            throw new AuthenticationException;
        }

        $tokens = DB::transaction(function () use ($user, $refreshToken, $issueTokens): array {
            $user->tokens()->where('name', $refreshToken->name)->delete();

            return $issueTokens->execute($user, $refreshToken->name);
        });

        return response()->json([
            'data' => [
                ...$tokens,
                'user' => new UserResource($user),
            ],
            'message' => 'Token refreshed',
        ]);
    }

    public function me(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new UserResource($user->load('driver'));
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $token = $user->currentAccessToken();

        // The device's refresh token goes too, so the session cannot be renewed.
        if ($token instanceof PersonalAccessToken) {
            $user->tokens()->where('name', $token->name)->delete();
        } else {
            $token?->delete();
        }

        return response()->json([
            'data' => null,
            'message' => 'Logged out successfully',
        ]);
    }
}
