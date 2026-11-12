export interface DriverAccount {
    id: number;
    name: string;
    username: string | null;
    phone_number: string | null;
    enabled: boolean;
    created_at: string | null;
    /** Disabling the account leaves these customers without a driver. */
    customers_count: number;
}

export type DriverAccountStatusFilter = 'enabled' | 'disabled' | null;
