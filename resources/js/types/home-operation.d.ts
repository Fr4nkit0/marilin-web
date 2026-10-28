/** Where the unit price of a sale at home comes from: the customer's agreement or the list price. */
export type HomePriceSource = 'agreement' | 'list';

export interface HomeProduct {
    id: number;
    name: string;
    unitPrice: number;
    source: HomePriceSource;
}

export interface HomePaymentMethod {
    id: number;
    name: string;
}

export interface HomeContainerType {
    id: number;
    description: string;
}
