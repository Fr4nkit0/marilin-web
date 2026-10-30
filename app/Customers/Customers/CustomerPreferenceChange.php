<?php

declare(strict_types=1);

namespace App\Customers\Customers;

use App\Accounts\Models\User;
use Carbon\CarbonInterface;

final readonly class CustomerPreferenceChange
{
    /**
     * @param  int  $dayOfWeek  0 = Sunday, 6 = Saturday
     * @param  ?string  $timeWindowStart  H:i, null for any time of the day
     * @param  ?string  $timeWindowEnd  H:i, after the start when both are set
     * @param  bool  $enabled  false removes the preference
     * @param  ?string  $clientUuid  adopted only when the preference has none yet
     * @param  ?CarbonInterface  $appModifiedAt  set when the change comes from the app: it dates the row and marks it as received now
     */
    public function __construct(
        public int $dayOfWeek,
        public ?string $timeWindowStart,
        public ?string $timeWindowEnd,
        public bool $enabled,
        public User $modifiedBy,
        public ?string $clientUuid = null,
        public ?CarbonInterface $appModifiedAt = null,
    ) {}
}
