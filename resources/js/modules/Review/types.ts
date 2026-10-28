export type ReviewType = 'visit' | 'sale' | 'payment' | 'route_stop' | 'customer' | 'container_movement' | 'customer_agreement';
export type ReviewStatus = 'pending' | 'reviewed' | 'all';

export interface ReviewItem {
    type: ReviewType;
    id: number;
    driver: string | null;
    customer: string;
    occurred_at: string;
    reason: string | null;
    reviewed_at: string | null;
    reviewed_by: string | null;
}

export interface ReviewFilters {
    driver_id: number | null;
    from: string | null;
    to: string | null;
    type: ReviewType | null;
    status: ReviewStatus;
}
