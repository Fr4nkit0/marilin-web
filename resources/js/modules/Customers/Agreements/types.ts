/** Whether the agreement sets the price today, has not started yet, or is over. */
export type AgreementStatus = 'active' | 'upcoming' | 'ended';

export interface CustomerAgreementRow {
    id: number;
    product: string | null;
    unitPrice: number;
    validFrom: string;
    validTo: string | null;
    reason: string | null;
    createdBy: string | null;
    status: AgreementStatus;
}

export interface AgreementProduct {
    id: number;
    name: string;
    price: number;
}
