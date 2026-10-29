<?php

declare(strict_types=1);

namespace App\Customers\Customers;

use DomainException;

/**
 * A change that would break a rule of the customer's details; nothing of it is saved.
 */
class InvalidCustomerChange extends DomainException {}
