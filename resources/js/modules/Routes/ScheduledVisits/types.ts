/** What the admin reads of a scheduled visit, derived from its stop on the server. */
export type ScheduledVisitStatus = 'SCHEDULED' | 'ON_ROUTE' | 'VISITED' | 'NOT_VISITED' | 'CANCELLED';

/** A scheduled visit as the customer page lists it; the date is aaaa-mm-dd and the time hh:mm. */
export interface ScheduledVisitRow {
    id: number;
    date: string;
    scheduledTime: string | null;
    note: string | null;
    status: ScheduledVisitStatus;
    /** Agendada, En ruta, Visitada, Sin visitar or Cancelada. */
    statusLabel: string;
    /** Only a visit not done yet (Agendada or En ruta) can be cancelled. */
    canCancel: boolean;
}

/** The schedule form; an empty time or note is sent as none. */
export interface ScheduledVisitInput {
    date: string;
    scheduled_time: string;
    note: string;
}
