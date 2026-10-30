<?php

declare(strict_types=1);

namespace App\Customers\Customers;

use DomainException;

/**
 * A change that would break a rule of the customer's details; nothing of it is saved.
 * The field names the input the rule is about, so a form can show the message next to it.
 */
class InvalidCustomerChange extends DomainException
{
    public function __construct(string $message, public readonly ?string $field = null)
    {
        parent::__construct($message);
    }

    /**
     * The same rule, about a field nested under `$prefix` (the second preference row is `preferences.1`).
     */
    public function under(string $prefix): self
    {
        return new self($this->getMessage(), $this->field === null ? $prefix : "{$prefix}.{$this->field}");
    }
}
