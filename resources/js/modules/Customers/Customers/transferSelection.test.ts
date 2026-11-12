import { describe, expect, it } from 'vitest';
import type { TransferCustomer } from './types';
import { NO_NEIGHBORHOOD, allShownSelected, neighborhoodsOf, shownCustomers, toggleCustomer, toggleShown } from './transferSelection';

function customer(id: number, neighborhood: string | null): TransferCustomer {
    return { id, name: `Cliente ${id}`, street: null, neighborhood };
}

const customers = [customer(1, 'Norte'), customer(2, 'Centro'), customer(3, null), customer(4, 'Centro')];

describe('transferSelection', () => {
    it('lists each neighborhood once, sorted, and "sin barrio" last when a customer has none', () => {
        expect(neighborhoodsOf(customers)).toEqual(['Centro', 'Norte', NO_NEIGHBORHOOD]);
        expect(neighborhoodsOf([customer(1, 'Norte')])).toEqual(['Norte']);
    });

    it('shows every customer without a neighborhood filter, or only the chosen neighborhood', () => {
        expect(shownCustomers(customers, '').map((c) => c.id)).toEqual([1, 2, 3, 4]);
        expect(shownCustomers(customers, 'Centro').map((c) => c.id)).toEqual([2, 4]);
        expect(shownCustomers(customers, NO_NEIGHBORHOOD).map((c) => c.id)).toEqual([3]);
    });

    it('toggles one customer in and out of the selection', () => {
        expect(toggleCustomer([1], 2)).toEqual([1, 2]);
        expect(toggleCustomer([1, 2], 1)).toEqual([2]);
    });

    it('selects all the shown customers keeping the others, and unselects only the shown ones', () => {
        const centro = shownCustomers(customers, 'Centro');

        expect(allShownSelected([1], centro)).toBe(false);
        expect(toggleShown([1], centro)).toEqual([1, 2, 4]);
        expect(allShownSelected([1, 2, 4], centro)).toBe(true);
        expect(toggleShown([1, 2, 4], centro)).toEqual([1]);
        expect(allShownSelected([], [])).toBe(false);
    });
});
