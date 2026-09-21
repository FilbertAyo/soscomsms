<?php

use App\Enums\CreditTransactionType;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\PricingTierSeeder;

function billingCustomer(): array
{
    $user = User::factory()->create();
    $customer = Customer::factory()->complete()->create([
        'user_id' => $user->id,
        'sms_balance' => 9,
    ]);

    return [$user, $customer];
}

beforeEach(function () {
    $this->seed(PricingTierSeeder::class);
});

it('prices purchases below 500 SMS at the first range unit price', function () {
    [$user, $customer] = billingCustomer();

    $this->actingAs($user)
        ->postJson('/api/payments', [
            'quantity' => 50,
            'payer_reference' => '0754221890',
        ])
        ->assertCreated()
        ->assertJsonPath('payment.quantity', 50)
        ->assertJsonPath('payment.unitPriceTzs', 30)
        ->assertJsonPath('payment.amountTzs', 1500)
        ->assertJsonPath('smsBalance', 9);

    expect($customer->fresh()->sms_balance)->toBe(9);
});

it('prices a custom SMS quantity at the matching volume range', function () {
    [$user, $customer] = billingCustomer();

    $this->actingAs($user)
        ->postJson('/api/payments', [
            'quantity' => 3500,
            'payer_reference' => '0754221890',
        ])
        ->assertCreated()
        ->assertJsonPath('payment.quantity', 3500)
        ->assertJsonPath('payment.unitPriceTzs', 25)
        ->assertJsonPath('payment.amountTzs', 87500)
        ->assertJsonPath('smsBalance', 9);

    expect($customer->fresh()->sms_balance)->toBe(9);
});

it('creates a pending Lipa namba purchase without changing the SMS balance', function () {
    [$user, $customer] = billingCustomer();

    $this->actingAs($user)
        ->postJson('/api/payments', [
            'quantity' => 10000,
            'payer_reference' => '0754221890',
        ])
        ->assertCreated()
        ->assertJsonPath('payment.status', PaymentStatus::Pending->value)
        ->assertJsonPath('payment.quantity', 10000)
        ->assertJsonPath('payment.unitPriceTzs', 22)
        ->assertJsonPath('payment.amountTzs', 220000)
        ->assertJsonPath('payment.lipaNamba', '123456')
        ->assertJsonPath('smsBalance', 9);

    expect($customer->fresh()->sms_balance)->toBe(9)
        ->and($customer->creditTransactions()->where('type', CreditTransactionType::Purchase)->count())->toBe(0);
});

it('lets an admin confirm payment and credits the quoted package', function () {
    [$user, $customer] = billingCustomer();
    $admin = User::factory()->admin()->create();

    $create = $this->actingAs($user)
        ->postJson('/api/payments', [
            'quantity' => 10000,
            'payer_reference' => 'Amina Juma',
        ])
        ->assertCreated();

    $paymentId = $create->json('payment.id');

    $this->actingAs($admin)
        ->postJson("/api/admin/payments/{$paymentId}/confirmation")
        ->assertOk()
        ->assertJsonPath('payment.status', PaymentStatus::Paid->value)
        ->assertJsonPath('smsBalance', 10009);

    expect($customer->fresh()->sms_balance)->toBe(10009);

    $purchase = $customer->creditTransactions()
        ->where('type', CreditTransactionType::Purchase)
        ->first();

    expect($purchase)->not->toBeNull()
        ->and($purchase->quantity)->toBe(10000)
        ->and($purchase->customer_unit_price)->toBe(22)
        ->and($purchase->amount_tzs)->toBe(220000)
        ->and($purchase->reference)->toBe('PAY-'.$paymentId);
});

it('rejects a pending payment without issuing credits', function () {
    [$user, $customer] = billingCustomer();
    $admin = User::factory()->admin()->create();

    $create = $this->actingAs($user)
        ->postJson('/api/payments', [
            'quantity' => 1000,
            'payer_reference' => '0754221890',
        ])
        ->assertCreated();

    $this->actingAs($admin)
        ->postJson('/api/admin/payments/'.$create->json('payment.id').'/rejection', [
            'rejection_reason' => 'No Lipa namba payment received.',
        ])
        ->assertOk()
        ->assertJsonPath('payment.status', PaymentStatus::Rejected->value);

    expect($customer->fresh()->sms_balance)->toBe(9)
        ->and($customer->creditTransactions()->where('type', CreditTransactionType::Purchase)->count())->toBe(0);
});

it('expires due pending payments without crediting the wallet', function () {
    [, $customer] = billingCustomer();

    $payment = Payment::factory()->create([
        'customer_id' => $customer->id,
        'status' => PaymentStatus::Pending,
        'expires_at' => now()->subHour(),
    ]);

    $this->artisan('payments:expire')->assertSuccessful();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Expired)
        ->and($customer->fresh()->sms_balance)->toBe(9);
});

it('shows Lipa namba and account name for the buy SMS quote', function () {
    [$user] = billingCustomer();

    $this->actingAs($user)
        ->getJson('/api/wallet')
        ->assertOk()
        ->assertJsonPath('lipaNamba', '123456')
        ->assertJsonPath('lipaNambaName', 'SOSCOM TECHNOLOGIES COMPANY LIMITED');

    $this->actingAs($user)
        ->getJson('/api/payments')
        ->assertOk()
        ->assertJsonPath('lipaNamba', '123456')
        ->assertJsonPath('lipaNambaName', 'SOSCOM TECHNOLOGIES COMPANY LIMITED');
});
