/** Where a record was made: at a route stop, or on its own from the customer's page in the app. */
export type CashCloseOrigin = 'stop' | 'loose';

export interface CashClosePaymentRow {
    id: number;
    customer: string | null;
    at: string | null;
    amount: number;
    concept: string;
    origin: CashCloseOrigin;
}

export interface CashCloseCreditRow {
    id: number;
    customer: string | null;
    at: string | null;
    amount: number;
    origin: CashCloseOrigin;
}

export interface CashCloseContainerRow {
    id: number;
    customer: string | null;
    at: string | null;
    container: string | null;
    movement: 'DELIVERED' | 'RETURNED' | string;
    quantity: number;
    origin: CashCloseOrigin;
}

export interface CashCloseDetails {
    cash: CashClosePaymentRow[];
    transfer: CashClosePaymentRow[];
    credit: CashCloseCreditRow[];
    containers: CashCloseContainerRow[];
}

export type CashCloseDetailKind = keyof CashCloseDetails;

export interface SettlementData {
    routeId: number;
    status: string;
    totalSales: number;
    creditSales: number;
    cashAmount: number;
    transferAmount: number;
    totalCollected: number;
    debtCollected: number;
    containersDelivered: number;
    containersReturned: number;
    stockLoaded: { product: string; quantity: number; loads: number }[];
    stockRemaining: { product: string; quantity: number }[];
    details: CashCloseDetails;
}
