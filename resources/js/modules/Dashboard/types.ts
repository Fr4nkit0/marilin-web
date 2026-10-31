export interface RepartidorStatus {
    id: number;
    name: string;
    status: string;
    totalStops: number;
    completedStops: number;
    progressPercent: number;
    totalSales: number;
    totalCollected: number;
    startedAt?: string;
    completedAt?: string;
}

export interface CasaStatus {
    name: string;
    totalSales: number;
    totalCollected: number;
}

export interface DashboardSaleRow {
    id: number;
    customer: string | null;
    driver: string | null;
    at: string | null;
    amount: number;
    credit: boolean;
}

export interface DashboardPaymentRow {
    id: number;
    customer: string | null;
    driver: string | null;
    at: string | null;
    method: 'CASH' | 'TRANSFER' | string;
    amount: number;
}

export interface DashboardStopRow {
    id: number;
    driver: string | null;
    customer: string | null;
    time: string | null;
    status: 'PENDING' | 'IN_PROGRESS' | 'FINISHED' | 'CANCELLED' | 'RESCHEDULED';
    done: boolean;
}

export interface DashboardContainerRow {
    id: number;
    customer: string | null;
    driver: string | null;
    at: string | null;
    container: string | null;
    movement: 'DELIVERED' | 'RETURNED' | string;
    quantity: number;
}

export interface DashboardDetails {
    sales: DashboardSaleRow[];
    payments: DashboardPaymentRow[];
    stops: DashboardStopRow[];
    containers: DashboardContainerRow[];
}

export type DashboardDetailKind = keyof DashboardDetails;

export interface DriverSyncStatus {
    id: number;
    name: string;
    lastSyncAt: string | null;
    onRoute: boolean;
    stale: boolean;
}

export interface DashboardMetrics {
    date: string;
    kpis: {
        totalSales: number;
        salesDelta: number;
        /** Every confirmed payment of the day (cash, transfer and card): the same "cobrado" Ventas y cobros and the cash close show. */
        totalCollected: number;
        cashPayments: number;
        transferPayments: number;
        cardPayments: number;
        totalStops: number;
        completedStops: number;
        stopsProgressPercent: number;
        containersDelivered: number;
        containersReturned: number;
    };
    repartidores: RepartidorStatus[];
    casa: CasaStatus;
    details: DashboardDetails;
    sync: DriverSyncStatus[];
}
