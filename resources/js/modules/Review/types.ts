export type ReviewType =
    | 'visit'
    | 'sale'
    | 'payment'
    | 'route_stop'
    | 'customer'
    | 'container_movement'
    | 'customer_agreement'
    | 'customer_change';
export type ReviewStatus = 'pending' | 'reviewed' | 'all';

export type CustomerChangeEntity = 'customer' | 'customer_address' | 'customer_preference';
export type CustomerChangeStatus = 'PENDING' | 'APPROVED' | 'REJECTED';
export type CustomerChangeValue = string | number | boolean | null;

/** A driver's change to another driver's customer: it waits until the admin approves or rejects it. */
export interface CustomerChange {
    entity: CustomerChangeEntity;
    status: CustomerChangeStatus;
    /** It adds an address or a preference instead of editing one. */
    adds: boolean;
    /** Each proposed field next to its value now; `current` is null when the change adds the address or preference. Address fields of a customer change are `address.<field>`. */
    fields: { field: string; current: CustomerChangeValue; proposed: CustomerChangeValue }[];
}

export interface ReviewItem {
    type: ReviewType;
    id: number;
    driver: string | null;
    customer: string;
    occurred_at: string;
    /** Every reason the record waits for review, in the panel's language and in the order they were raised. */
    reasons: string[];
    reviewed_at: string | null;
    reviewed_by: string | null;
    /** A pending sale that left the truck below zero: its route and the truck stock of what it sold; null otherwise. */
    stock_correction: StockCorrection | null;
    /** Only for `customer_change` rows. */
    change: CustomerChange | null;
}

export interface StockCorrection {
    route_id: number;
    products: { id: number; name: string; current: number }[];
}

export interface ReviewFilters {
    driver_id: number | null;
    from: string | null;
    to: string | null;
    type: ReviewType | null;
    status: ReviewStatus;
}
