import type { StopStatus } from '@/lib/format';

export type { StopStatus };

export type RouteStatus = 'PLANNED' | 'IN_PROGRESS' | 'COMPLETED' | 'CANCELLED';

export interface StopCustomer {
    id: number;
    name: string;
    address?: string;
    phone?: string;
}

/** What every screen shows of a route stop; each page adds its own figures. */
export interface RouteStopRow {
    id: number;
    customer: StopCustomer;
    status: StopStatus;
    /** "Parada hecha": the visit finished, whatever its outcome. */
    done: boolean;
    arrivedAt?: string;
    /** What the driver wrote at the visit. */
    notes?: string;
    /** "Visita solicitada": the admin scheduled this visit. */
    is_requested: boolean;
    /** The admin's note for a requested visit. */
    note: string | null;
}

/** "Repartir ruta": a stop of the absent driver's route that can still go to another driver. */
export interface SplitStop {
    id: number;
    customer: string;
    street: string | null;
    neighborhood: string | null;
    /** "HH:MM", when the stop has a time. */
    scheduledTime: string | null;
}

/** Another driver whose route of the same date already has stops in the neighborhood. */
export interface SplitSuggestion {
    driverId: number;
    driverName: string;
    stops: number;
}

/** The route's movable stops of one neighborhood; `neighborhood` is null for "Sin barrio". */
export interface SplitGroup {
    neighborhood: string | null;
    name: string;
    stops: SplitStop[];
    /** The busiest first; empty for "Sin barrio". */
    suggestions: SplitSuggestion[];
}

export interface SplitRoute {
    id: number;
    driverName: string;
    /** "dd/mm/yyyy". */
    date: string;
    /** "yyyy-mm-dd", to go back to the routes of that day. */
    rawDate: string;
}
