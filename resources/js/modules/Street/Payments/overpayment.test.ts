import { describe, expect, it } from 'vitest';
import { overpayment } from './overpayment';

describe('overpayment', () => {
    it('is zero while the amount does not exceed the debt', () => {
        expect(overpayment(4000, 6500)).toBe(0);
        expect(overpayment(6500, 6500)).toBe(0);
        expect(overpayment(0, 6500)).toBe(0);
    });

    it('is what the amount exceeds the debt by', () => {
        expect(overpayment(13000, 6500)).toBe(6500);
        expect(overpayment(100.5, 100.25)).toBe(0.25);
    });

    it('is the whole amount when there is no debt or the customer already has credit', () => {
        expect(overpayment(6500, 0)).toBe(6500);
        expect(overpayment(6500, -1000)).toBe(6500);
    });

    it('is zero for an empty or invalid amount', () => {
        expect(overpayment(Number.NaN, 6500)).toBe(0);
    });
});
