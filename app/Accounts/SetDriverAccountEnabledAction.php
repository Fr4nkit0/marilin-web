<?php

declare(strict_types=1);

namespace App\Accounts;

use App\Accounts\Models\User;
use App\Customers\Customers\DriverAssignment;
use Illuminate\Support\Facades\DB;

/**
 * Switches a driver account on or off. Switching it off also ends every open session:
 * the app tokens are revoked and the stored web sessions are removed. Its customers are left without a driver,
 * so the admin hands them to another one; enabling it again gives none back.
 */
final class SetDriverAccountEnabledAction
{
    public function __construct(private readonly DriverAssignment $assignment) {}

    public function execute(User $user, bool $enabled, User $changedBy): void
    {
        DB::transaction(function () use ($user, $enabled, $changedBy): void {
            $user->update(['enabled' => $enabled]);
            $user->driver?->update(['enabled' => $enabled]);

            if (! $enabled) {
                $user->tokens()->delete();
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();

                if ($user->driver !== null) {
                    $this->assignment->unassignAll($user->driver->id, $changedBy);
                }
            }
        });
    }
}
