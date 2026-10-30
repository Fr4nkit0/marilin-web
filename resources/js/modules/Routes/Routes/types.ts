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
