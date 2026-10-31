<?php

declare(strict_types=1);

namespace App\Street\RouteStock\Enums;

/**
 * What a row of the truck load history is: goods put on the truck, or the admin fixing a load that was written down wrong.
 */
enum LoadType: string
{
    case Load = 'load';
    case Correction = 'correction';
}
