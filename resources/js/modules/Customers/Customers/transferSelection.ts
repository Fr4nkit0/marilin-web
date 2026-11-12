import type { TransferCustomer } from './types';

/** The filter value of the customers whose primary address has no neighborhood. */
export const NO_NEIGHBORHOOD = 'Sin barrio';

/** The neighborhoods of the customers, each once and sorted, with "Sin barrio" last when some customer has none. */
export function neighborhoodsOf(customers: TransferCustomer[]): string[] {
    const named = [...new Set(customers.flatMap((c) => (c.neighborhood ? [c.neighborhood] : [])))].sort((a, b) => a.localeCompare(b, 'es'));

    return customers.some((c) => !c.neighborhood) ? [...named, NO_NEIGHBORHOOD] : named;
}

/** The customers of a neighborhood; an empty filter shows them all. */
export function shownCustomers(customers: TransferCustomer[], neighborhood: string): TransferCustomer[] {
    if (neighborhood === '') return customers;

    return customers.filter((c) => (neighborhood === NO_NEIGHBORHOOD ? !c.neighborhood : c.neighborhood === neighborhood));
}

export function toggleCustomer(selected: number[], id: number): number[] {
    return selected.includes(id) ? selected.filter((s) => s !== id) : [...selected, id];
}

export function allShownSelected(selected: number[], shown: TransferCustomer[]): boolean {
    return shown.length > 0 && shown.every((c) => selected.includes(c.id));
}

/** "Seleccionar todos" of the shown customers: selects them all, or unselects them when they all are; the hidden ones keep their state. */
export function toggleShown(selected: number[], shown: TransferCustomer[]): number[] {
    const ids = shown.map((c) => c.id);

    if (allShownSelected(selected, shown)) return selected.filter((id) => !ids.includes(id));

    return [...selected, ...ids.filter((id) => !selected.includes(id))];
}
