/** Where the unit price of a sale at home comes from: the customer's agreement or the list price. */
export type HomePriceSource = 'agreement' | 'list';

export interface HomeProduct {
    id: number;
    name: string;
    unitPrice: number;
    source: HomePriceSource;
}

/** A payment of a sale still in force, as the sale's cancel modal lists it. */
export interface LinkedPayment {
    id: number;
    amount: string | number;
    method: string | null;
}

export interface HomePaymentMethod {
    id: number;
    name: string;
}

export interface HomeContainerType {
    id: number;
    description: string;
}
