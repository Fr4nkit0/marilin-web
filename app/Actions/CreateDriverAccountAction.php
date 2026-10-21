<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates a driver's login together with its driver record, so the account can use the app at once.
 */
final class CreateDriverAccountAction
{
    /**
     * @param  array{name: string, email: string, username?: ?string, phone_number?: ?string, password: string}  $data
     */
    public function execute(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'username' => $data['username'] ?? null,
                'phone_number' => $data['phone_number'] ?? null,
                'password' => $data['password'],
                'is_super_user' => false,
                'enabled' => true,
            ]);

            $user->driver()->create(['enabled' => true]);

            return $user;
        });
    }
}
