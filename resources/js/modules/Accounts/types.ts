export interface DriverAccount {
    id: number;
    name: string;
    email: string;
    username: string | null;
    phone_number: string | null;
    enabled: boolean;
    created_at: string | null;
}

export type DriverAccountStatusFilter = 'enabled' | 'disabled' | null;
