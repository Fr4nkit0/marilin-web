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
    notes?: string;
}
