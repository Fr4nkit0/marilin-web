<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Switches a driver account on or off. Switching it off also ends every open session:
 * the app tokens are revoked and the stored web sessions are removed.
 */
final class SetDriverAccountEnabledAction
{
    public function execute(User $user, bool $enabled): void
    {
        DB::transaction(function () use ($user, $enabled): void {
            $user->update(['enabled' => $enabled]);
            $user->driver?->update(['enabled' => $enabled]);

            if (! $enabled) {
                $user->tokens()->delete();
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }
        });
    }
}
