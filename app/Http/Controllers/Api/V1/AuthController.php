<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
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

        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => new UserResource($user),
            ],
            'message' => 'Login successful',
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
        $user->currentAccessToken()?->delete();

        return response()->json([
            'data' => null,
            'message' => 'Logged out successfully',
        ]);
    }
}
