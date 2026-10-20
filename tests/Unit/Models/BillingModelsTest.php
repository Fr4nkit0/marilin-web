<?php

use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\CustomerAgreement;
use App\Models\CustomerBalance;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Transaction;
use App\Models\User;

test('customer agreement defines agreed price and active scope', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();

    $agreement = CustomerAgreement::factory()->create([
        'customer_id' => $customer->id,
        'product_id' => $product->id,
        'unit_price' => 1500.00,
        'valid_from' => now()->subMonth()->toDateString(),
        'valid_to' => now()->addMonth()->toDateString(),
    ]);

    expect($agreement->customer->id)->toBe($customer->id);
    expect($agreement->product->id)->toBe($product->id);
    expect($agreement->unit_price)->toBe('1500.00');
    expect(CustomerAgreement::active()->count())->toBe(1);
});

test('sales and sale details compute totals and link products', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $user = User::factory()->create();

    $sale = Sale::factory()->create([
        'customer_id' => $customer->id,
        'total' => 3000.00,
        'status' => 'confirmed',
        'created_by' => $user->id,
    ]);

    $detail = SaleDetail::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 1500.00,
    ]);

    expect($detail->fresh()->quantity)->toBe(2);

    expect($sale->details)->toHaveCount(1);
    expect($sale->customer->id)->toBe($customer->id);
    expect($detail->sale->id)->toBe($sale->id);
    expect($detail->product->id)->toBe($product->id);
    expect(Sale::confirmed()->count())->toBe(1);
});

test('payments connect transaction, payment method, sale, and customer', function () {
    $customer = Customer::factory()->create();
    $user = User::factory()->create();
    $method = PaymentMethod::factory()->efectivo()->create();
    $tx = Transaction::factory()->in()->create(['amount' => 3000.00]);
    $sale = Sale::factory()->create(['customer_id' => $customer->id, 'created_by' => $user->id]);

    $payment = Payment::factory()->create([
        'transaction_id' => $tx->id,
        'customer_id' => $customer->id,
        'sale_id' => $sale->id,
        'payment_method_id' => $method->id,
        'amount' => 3000.00,
        'received_by' => $user->id,
        'status' => 'CONFIRMED',
    ]);

    expect($payment->transaction->id)->toBe($tx->id);
    expect($payment->customer->id)->toBe($customer->id);
    expect($payment->sale->id)->toBe($sale->id);
    expect($payment->paymentMethod->id)->toBe($method->id);
    expect($payment->recipient->id)->toBe($user->id);
    expect(Payment::confirmed()->count())->toBe(1);
});

test('customer account entries and balance maintain ledger history', function () {
    $customer = Customer::factory()->create();
    $user = User::factory()->create();

    $entry = CustomerAccountEntry::factory()->saleEntry()->create([
        'customer_id' => $customer->id,
        'amount' => 3000.00,
        'created_by' => $user->id,
    ]);

    $balance = CustomerBalance::factory()->create([
        'customer_id' => $customer->id,
        'current_balance' => 3000.00,
        'last_entry_id' => $entry->id,
        'created_by' => $user->id,
    ]);

    expect($customer->accountEntries)->toHaveCount(1);
    expect($customer->balance->id)->toBe($balance->id);
    expect($balance->lastEntry->id)->toBe($entry->id);
    expect(CustomerAccountEntry::sales()->count())->toBe(1);
});
