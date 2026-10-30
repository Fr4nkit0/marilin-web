export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    is_super_user: boolean;
}

/** What the header bell shows; only users who see the review menu get it. */
export interface Notifications {
    /** On route today and the app never reached the server, or not for the last half hour. */
    unsyncedDrivers: { id: number; name: string; lastSyncAt: string | null }[];
    /** Records waiting for review, as the review inbox counts them. */
    pendingReview: number;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    notifications?: Notifications;
    /** What the last action said before redirecting, shown once as a toast. */
    flash?: { success: string | null };
};

export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}
