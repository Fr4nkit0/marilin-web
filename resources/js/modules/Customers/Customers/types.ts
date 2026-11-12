/** A delivery preference as the customer page shows it; times are hh:mm, null for an open end. */
export interface CustomerPreferenceRow {
    id: number;
    /** 0 = Sunday, 6 = Saturday. */
    dayOfWeek: number;
    timeWindowStart: string | null;
    timeWindowEnd: string | null;
}

/** A delivery preference as a form holds it; an empty time is no limit on that end. */
export interface PreferenceInput {
    day_of_week: string;
    time_window_start: string;
    time_window_end: string;
}

/** A customer of the driver whose portfolio is being transferred; the street and neighborhood are its primary address's. */
export interface TransferCustomer {
    id: number;
    name: string;
    street: string | null;
    neighborhood: string | null;
}

/** A driver as a select offers it. */
export interface DriverOption {
    id: number;
    name: string;
}
