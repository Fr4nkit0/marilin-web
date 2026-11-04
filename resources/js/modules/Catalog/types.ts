export interface ProductItem {
    id: number;
    name: string;
    description: string | null;
    price: string;
    enabled: boolean;
}

export interface ContainerTypeItem {
    id: number;
    code: string;
    description: string;
    enabled: boolean;
}

export interface PaymentMethodItem {
    id: number;
    code: string;
    name: string;
    enabled: boolean;
}
