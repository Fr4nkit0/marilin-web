<?php

declare(strict_types=1);

namespace App\Accounts\Http\Requests\Api\V1;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * The app's login, throttled like the web one: 5 failed attempts per minute per email (any letter case) and IP.
 */
class LoginRequest extends FormRequest
{
    private const int MAX_ATTEMPTS = 5;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * A locked email and IP is answered 429 with the seconds to wait, in the `Retry-After` header and in `retry_after`,
     * and the web's message under `errors.email`, so the app can show it like any other login error.
     *
     * @throws HttpResponseException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());
        $message = trans('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]);

        throw new HttpResponseException(response()->json([
            'message' => $message,
            'errors' => ['email' => [$message]],
            'retry_after' => $seconds,
        ], 429, ['Retry-After' => $seconds]));
    }

    public function hitRateLimit(): void
    {
        RateLimiter::hit($this->throttleKey());
    }

    public function clearRateLimit(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
