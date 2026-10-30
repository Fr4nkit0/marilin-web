/**
 * How much a payment exceeds what the customer owes; that part stays as credit in favor of the customer.
 * A balance of zero or below owes nothing, so the whole amount exceeds it.
 */
export function overpayment(amount: number, balance: number): number {
    if (!Number.isFinite(amount) || amount <= 0) return 0;

    const cents = Math.round(amount * 100) - Math.max(Math.round(balance * 100), 0);
    return Math.max(cents, 0) / 100;
}
