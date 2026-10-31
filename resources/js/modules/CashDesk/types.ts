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

/** A sale made after the route was completed: it still took stock from the truck, the close shows it apart. */
export interface CashClosePostCloseRow {
    id: number;
    customer: string | null;
    at: string | null;
    amount: number;
    credit: boolean;
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
    card: CashClosePaymentRow[];
    credit: CashCloseCreditRow[];
    postClose: CashClosePostCloseRow[];
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
    cardAmount: number;
    /** Every confirmed payment of the day (cash, transfer and card): the same "cobrado" the panel and Ventas y cobros show. */
    totalCollected: number;
    debtCollected: number;
    containersDelivered: number;
    containersReturned: number;
    /** Total of the sales made after the route was completed; they are already inside totalSales. */
    postCloseSales: number;
    stockLoaded: { product: string; quantity: number; loads: number }[];
    stockRemaining: { product: string; quantity: number }[];
    details: CashCloseDetails;
}
