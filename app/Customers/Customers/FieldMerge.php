<?php

declare(strict_types=1);

namespace App\Customers\Customers;

/**
 * What an offline edit did to the fields it carried: the ones it applied and the ones it lost because the server
 * already held a newer change. A field the server already holds at that very moment (a replay) is neither.
 */
final readonly class FieldMerge
{
    /**
     * @param  list<string>  $applied
     * @param  list<string>  $outdated
     */
    public function __construct(
        public array $applied = [],
        public array $outdated = [],
        public int $carried = 0,
    ) {}

    public function plus(self $other): self
    {
        return new self(
            [...$this->applied, ...$other->applied],
            [...$this->outdated, ...$other->outdated],
            $this->carried + $other->carried,
        );
    }

    public function changedAny(): bool
    {
        return $this->applied !== [];
    }

    public function wasApplied(string $field): bool
    {
        return in_array($field, $this->applied, true);
    }

    /**
     * Every field the edit carried was older than the server's: the edit was dropped as a whole.
     */
    public function isOutdated(): bool
    {
        return $this->carried > 0 && count($this->outdated) === $this->carried;
    }
}
