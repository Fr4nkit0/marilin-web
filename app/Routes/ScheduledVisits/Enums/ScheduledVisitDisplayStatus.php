<?php

declare(strict_types=1);

namespace App\Routes\ScheduledVisits\Enums;

enum ScheduledVisitDisplayStatus: string
{
    case Scheduled = 'SCHEDULED';
    case OnRoute = 'ON_ROUTE';
    case Visited = 'VISITED';
    case NotVisited = 'NOT_VISITED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Agendada',
            self::OnRoute => 'En ruta',
            self::Visited => 'Visitada',
            self::NotVisited => 'Sin visitar',
            self::Cancelled => 'Cancelada',
        };
    }
}
