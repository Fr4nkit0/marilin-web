<?php

declare(strict_types=1);

namespace App\Accounts;

use Closure;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Arrayable;
use SensitiveParameter;

/**
 * The users provider of the web login and the password reset: the email is found whatever its letter case,
 * as it is stored (the stored email is never rewritten).
 */
class CaseInsensitiveUserProvider extends EloquentUserProvider
{
    /**
     * @param  array<string, mixed>  $credentials
     */
    public function retrieveByCredentials(#[SensitiveParameter] array $credentials): ?Authenticatable
    {
        $credentials = array_filter($credentials, fn (string $key): bool => ! str_contains($key, 'password'), ARRAY_FILTER_USE_KEY);

        if ($credentials === []) {
            return null;
        }

        $query = $this->newModelQuery();

        foreach ($credentials as $key => $value) {
            match (true) {
                $key === 'email' && is_string($value) => $query->emailIs($value),
                is_array($value) || $value instanceof Arrayable => $query->whereIn($key, $value),
                $value instanceof Closure => $value($query),
                default => $query->where($key, $value),
            };
        }

        return $query->first();
    }
}
