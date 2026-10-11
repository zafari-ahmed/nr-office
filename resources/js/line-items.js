/**
 * Quotation / invoice editor: line items, discount, tax and live totals.
 */
export function lineItems(initial) {
    return {
        items: initial.items.length ? initial.items : [{ description: '', quantity: 1, unit: '', rate: 0 }],
        discountType: initial.discountType || 'amount',
        discountValue: Number(initial.discountValue || 0),
        taxRate: Number(initial.taxRate || 0),
        currency: initial.currency || 'PKR',

        add() { this.items.push({ description: '', quantity: 1, unit: '', rate: 0 }); },
        remove(i) { if (this.items.length > 1) this.items.splice(i, 1); },
        amount(item) { return (Number(item.quantity) || 0) * (Number(item.rate) || 0); },
        get subtotal() { return this.items.reduce((sum, item) => sum + this.amount(item), 0); },
        get discount() {
            return this.discountType === 'percent'
                ? this.subtotal * (Number(this.discountValue) || 0) / 100
                : Math.min(Number(this.discountValue) || 0, this.subtotal);
        },
        get tax() { return (this.subtotal - this.discount) * (Number(this.taxRate) || 0) / 100; },
        get total() { return this.subtotal - this.discount + this.tax; },
        fmt(n) { return this.currency + ' ' + Number(n || 0).toLocaleString('en-PK', { maximumFractionDigits: 2 }); },
        num(n) { return Number(n || 0).toLocaleString('en-PK', { maximumFractionDigits: 2 }); },
    };
}
