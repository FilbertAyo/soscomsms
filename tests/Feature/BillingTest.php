<?php

use App\Enums\CreditTransactionType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\CreditTransaction;
use App\Models\Customer;
use App\Models\CustomerPrice;
use App\Models\Payment;
use App\Models\PricingTier;
use App\Models\User;
use App\Services\CreditLedger;
use Database\Seeders\PricingTierSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function billingCustomer(): array
{
    $user = User::factory()->create(['name' => 'Amina Juma']);
    $customer = Customer::factory()->complete()->create([
        'user_id' => $user->id,
        'sms_balance' => 9,
    ]);

    return [$user, $customer];
}

beforeEach(function () {
    $this->seed(PricingTierSeeder::class);
    configureSnippe();
    fakeSnippePayment();
});

it('prices the first SMS range at 25 TZS and rejects less than 100', function () {
    [$user, $customer] = billingCustomer();

    $this->actingAs($user)
        ->postJson('/api/payments', [
            'quantity' => 100,
            'payer_reference' => '0754221890',
        ])
        ->assertCreated()
        ->assertJsonPath('payment.quantity', 100)
        ->assertJsonPath('payment.unitPriceTzs', 25)
        ->assertJsonPath('payment.amountTzs', 2500)
        ->assertJsonPath('payment.method', PaymentMethod::Snippe->value)
        ->assertJsonPath('smsBalance', 9);

    $this->actingAs($user)
        ->postJson('/api/payments', [
            'quantity' => 49,
            'payer_reference' => '0754221890',
        ])
        ->assertUnprocessable();

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
        ->assertJsonPath('payment.unitPriceTzs', 23)
        ->assertJsonPath('payment.amountTzs', 80500)
        ->assertJsonPath('smsBalance', 9);

    expect($customer->fresh()->sms_balance)->toBe(9)
        ->and($customer->creditTransactions()->where('type', CreditTransactionType::Purchase)->count())->toBe(0);
});

it('starts a mobile money prompt without changing the SMS balance', function () {
    [$user, $customer] = billingCustomer();

    $this->actingAs($user)
        ->postJson('/api/payments', [
            'quantity' => 10000,
            'payer_reference' => '0754221890',
        ])
        ->assertCreated()
        ->assertJsonPath('payment.status', PaymentStatus::Pending->value)
        ->assertJsonPath('payment.payerReference', '+255754221890')
        ->assertJsonPath('payment.snippeReference', 'ref-pay-1')
        ->assertJsonPath('smsBalance', 9);

    Http::assertSent(function (Request $request) {
        $key = $request->header('Idempotency-Key')[0] ?? '';

        return $request->method() === 'POST'
            && $request->url() === 'https://api.snippe.sh/v1/payments'
            && strlen($key) <= 30
            && $request['details']['amount'] === 200000
            && $request['details']['currency'] === 'TZS'
            && $request['phone_number'] === '255754221890'
            && $request['payment_type'] === 'mobile';
    });

    expect($customer->fresh()->sms_balance)->toBe(9)
        ->and($customer->creditTransactions()->where('type', CreditTransactionType::Purchase)->count())->toBe(0);
});

it('rejects a phone number that is not a Tanzanian mobile', function () {
    [$user] = billingCustomer();

    $this->actingAs($user)
        ->postJson('/api/payments', [
            'quantity' => 100,
            'payer_reference' => '07123',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('payer_reference');

    Http::assertNothingSent();
});

it('refuses to start a payment when the Snippe key is missing', function () {
    config(['sms.snippe.api_key' => '']);
    [$user] = billingCustomer();

    $this->actingAs($user)
        ->postJson('/api/payments', [
            'quantity' => 100,
            'payer_reference' => '0754221890',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('quantity');

    Http::assertNothingSent();
});

it('credits the quoted package only after a signed completed webhook', function () {
    [$user, $customer] = billingCustomer();

    $create = $this->actingAs($user)
        ->postJson('/api/payments', [
            'quantity' => 100,
            'payer_reference' => '0754221890',
        ])
        ->assertCreated();

    $paymentId = (int) $create->json('payment.id');
    $event = snippeCompletedEvent('ref-pay-1', 2500, $paymentId);

    postSnippeWebhook($event)
        ->assertOk();

    expect($customer->fresh()->sms_balance)->toBe(109);

    $purchase = $customer->creditTransactions()
        ->where('type', CreditTransactionType::Purchase)
        ->first();

    expect($purchase)->not->toBeNull()
        ->and($purchase->quantity)->toBe(100)
        ->and($purchase->customer_unit_price)->toBe(25)
        ->and($purchase->amount_tzs)->toBe(2500)
        ->and($purchase->reference)->toBe('PAY-'.$paymentId)
        ->and(Payment::query()->find($paymentId)?->status)->toBe(PaymentStatus::Paid)
        ->and(Payment::query()->find($paymentId)?->fee_tzs)->toBe(63);
});

it('rejects a webhook with a bad signature and does not credit', function () {
    [$user, $customer] = billingCustomer();

    $create = $this->actingAs($user)
        ->postJson('/api/payments', [
            'quantity' => 100,
            'payer_reference' => '0754221890',
        ])
        ->assertCreated();

    postSnippeWebhook(
        snippeCompletedEvent('ref-pay-1', 2500, (int) $create->json('payment.id')),
        ['signature' => str_repeat('a', 64)],
    )->assertBadRequest();

    expect($customer->fresh()->sms_balance)->toBe(9);
});

it('rejects a stale webhook timestamp', function () {
    [$user, $customer] = billingCustomer();

    $create = $this->actingAs($user)
        ->postJson('/api/payments', [
            'quantity' => 100,
            'payer_reference' => '0754221890',
        ])
        ->assertCreated();

    postSnippeWebhook(
        snippeCompletedEvent('ref-pay-1', 2500, (int) $create->json('payment.id')),
        ['timestamp' => time() - 301],
    )->assertBadRequest();

    expect($customer->fresh()->sms_balance)->toBe(9);
});

it('credits a completed webhook only once', function () {
    [$user, $customer] = billingCustomer();

    $create = $this->actingAs($user)
        ->postJson('/api/payments', [
            'quantity' => 100,
            'payer_reference' => '0754221890',
        ])
        ->assertCreated();

    $event = snippeCompletedEvent('ref-pay-1', 2500, (int) $create->json('payment.id'));

    postSnippeWebhook($event)->assertOk();
    postSnippeWebhook($event)->assertOk();

    expect($customer->fresh()->sms_balance)->toBe(109)
        ->and($customer->creditTransactions()->where('type', CreditTransactionType::Purchase)->count())->toBe(1);
});

it('does not credit when the webhook amount does not match the quote', function () {
    [$user, $customer] = billingCustomer();

    $create = $this->actingAs($user)
        ->postJson('/api/payments', [
            'quantity' => 100,
            'payer_reference' => '0754221890',
        ])
        ->assertCreated();

    $paymentId = (int) $create->json('payment.id');

    postSnippeWebhook(snippeCompletedEvent('ref-pay-1', 9999, $paymentId))
        ->assertOk();

    expect($customer->fresh()->sms_balance)->toBe(9)
        ->and(Payment::query()->find($paymentId)?->status)->toBe(PaymentStatus::Failed);
});

it('does not credit a failed or expired webhook', function () {
    [$user, $customer] = billingCustomer();

    $failed = $this->actingAs($user)
        ->postJson('/api/payments', [
            'quantity' => 100,
            'payer_reference' => '0754221890',
        ])
        ->assertCreated();

    postSnippeWebhook([
        'id' => 'evt_failed',
        'type' => 'payment.failed',
        'data' => [
            'reference' => 'ref-pay-1',
            'status' => 'failed',
            'failure_reason' => 'Transaction declined by user',
            'amount' => ['value' => 2500, 'currency' => 'TZS'],
        ],
    ])->assertOk();

    expect(Payment::query()->find($failed->json('payment.id'))?->status)->toBe(PaymentStatus::Failed);

    fakeSnippePayment('ref-pay-2');

    $expired = $this->actingAs($user)
        ->postJson('/api/payments', [
            'quantity' => 100,
            'payer_reference' => '0754221890',
        ])
        ->assertCreated();

    postSnippeWebhook([
        'id' => 'evt_expired',
        'type' => 'payment.expired',
        'data' => [
            'reference' => 'ref-pay-2',
            'status' => 'expired',
            'amount' => ['value' => 2500, 'currency' => 'TZS'],
        ],
    ])->assertOk();

    expect($customer->fresh()->sms_balance)->toBe(9)
        ->and(Payment::query()->find($expired->json('payment.id'))?->status)->toBe(PaymentStatus::Expired);
});

it('credits a pending payment when the status check reports it completed', function () {
    [$user, $customer] = billingCustomer();

    $create = $this->actingAs($user)
        ->postJson('/api/payments', [
            'quantity' => 100,
            'payer_reference' => '0754221890',
        ])
        ->assertCreated();

    $paymentId = (int) $create->json('payment.id');

    stubSnippe([
        'https://api.snippe.sh/*' => Http::response([
            'status' => 'success',
            'data' => [
                'reference' => 'ref-pay-1',
                'status' => 'completed',
                'amount' => ['value' => 2500, 'currency' => 'TZS'],
                'channel' => ['provider' => 'airtel'],
            ],
        ]),
    ]);

    $this->actingAs($user)
        ->getJson('/api/payments/'.$paymentId)
        ->assertOk()
        ->assertJsonPath('payment.status', PaymentStatus::Paid->value)
        ->assertJsonPath('smsBalance', 109);

    expect($customer->fresh()->sms_balance)->toBe(109);
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

it('lists purchases without a manual payment number', function () {
    [$user] = billingCustomer();

    $this->actingAs($user)
        ->getJson('/api/wallet')
        ->assertOk()
        ->assertJsonMissingPath('lipaNamba');

    $this->actingAs($user)
        ->getJson('/api/payments')
        ->assertOk()
        ->assertJsonMissingPath('lipaNamba');
});

it('gives the buyer a receipt after a paid mobile money purchase', function () {
    [$user] = billingCustomer();
    [$otherUser] = billingCustomer();

    $create = $this->actingAs($user)
        ->postJson('/api/payments', [
            'quantity' => 100,
            'payer_reference' => '0754221890',
        ])
        ->assertCreated();

    $paymentId = (int) $create->json('payment.id');

    $this->actingAs($user)
        ->getJson("/api/payments/{$paymentId}/receipt")
        ->assertNotFound();

    postSnippeWebhook(snippeCompletedEvent('ref-pay-1', 2500, $paymentId))->assertOk();

    $this->actingAs($user)
        ->getJson("/api/payments/{$paymentId}/receipt")
        ->assertOk()
        ->assertJsonPath('receipt.receiptNumber', sprintf('RCP-%06d', $paymentId))
        ->assertJsonPath('receipt.status', 'paid')
        ->assertJsonPath('receipt.line.quantity', 100)
        ->assertJsonPath('receipt.line.amountTzs', 2500)
        ->assertJsonPath('receipt.line.unitPriceTzs', 25)
        ->assertJsonPath('receipt.paymentMethod', PaymentMethod::Snippe->value)
        ->assertJsonPath('receipt.paymentMethodLabel', 'Mobile money (mpesa)')
        ->assertJsonPath('receipt.transactionId', 'ref-pay-1')
        ->assertJsonPath('receipt.internalReference', 'PAY-'.$paymentId)
        ->assertJsonPath('receipt.payerReference', '+255754221890')
        ->assertJsonPath('receipt.billTo.name', 'Amina Juma')
        ->assertJsonPath('receipt.issuer.company', 'SOSCOM Technologies Company Limited');

    $this->actingAs($otherUser)
        ->getJson("/api/payments/{$paymentId}/receipt")
        ->assertForbidden();
});

it('records a cash grant at the customer price and puts that price on the receipt', function () {
    $admin = User::factory()->admin()->create();
    [$user, $customer] = billingCustomer();

    $this->actingAs($admin)
        ->postJson("/api/admin/customers/{$customer->id}/credits", [
            'quantity' => 100,
        ])
        ->assertCreated()
        ->assertJsonPath('transaction.amountTzs', 2500)
        ->assertJsonPath('transaction.customerUnitPrice', 25)
        ->assertJsonPath('smsBalance', 109);

    $payment = Payment::query()->where('customer_id', $customer->id)->first();

    expect($payment)->not->toBeNull()
        ->and($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->method)->toBe(PaymentMethod::Cash)
        ->and($payment->amount_tzs)->toBe(2500)
        ->and($payment->external_reference)->toBeNull()
        ->and($payment->confirmed_by)->toBe($admin->id)
        ->and($payment->credit_transaction_id)->not->toBeNull();

    $this->actingAs($user)
        ->getJson("/api/payments/{$payment->id}/receipt")
        ->assertOk()
        ->assertJsonPath('receipt.line.amountTzs', 2500)
        ->assertJsonPath('receipt.paymentMethod', PaymentMethod::Cash->value)
        ->assertJsonPath('receipt.paymentMethodLabel', 'Cash')
        ->assertJsonPath('receipt.transactionId', null)
        ->assertJsonPath('receipt.smsAdded', 100)
        ->assertJsonPath('receipt.balanceAfter', 109);

    $this->actingAs($admin)
        ->getJson("/api/admin/payments/{$payment->id}/receipt")
        ->assertOk()
        ->assertJsonPath('receipt.receiptNumber', sprintf('RCP-%06d', $payment->id));
});

it('stores the transaction id an admin enters when granting SMS', function () {
    $admin = User::factory()->admin()->create();
    [$user, $customer] = billingCustomer();

    $this->actingAs($admin)
        ->postJson("/api/admin/customers/{$customer->id}/credits", [
            'quantity' => 100,
            'payment_method' => PaymentMethod::Bank->value,
            'transaction_id' => 'BANK-4481',
            'note' => 'Paid at the counter',
        ])
        ->assertCreated();

    $payment = Payment::query()->where('customer_id', $customer->id)->first();

    expect($payment?->method)->toBe(PaymentMethod::Bank)
        ->and($payment?->external_reference)->toBe('BANK-4481')
        ->and($payment?->admin_note)->toBe('Paid at the counter');

    $this->actingAs($user)
        ->getJson("/api/payments/{$payment->id}/receipt")
        ->assertOk()
        ->assertJsonPath('receipt.transactionId', 'BANK-4481')
        ->assertJsonPath('receipt.paymentMethodLabel', 'Bank transfer')
        ->assertJsonPath('receipt.note', 'Paid at the counter');
});

it('prices a manual grant from the customer special price when the admin does not override it', function () {
    $admin = User::factory()->admin()->create();
    [$user, $customer] = billingCustomer();
    $tier = PricingTier::query()->where('min_quantity', 1000)->first();

    CustomerPrice::query()->create([
        'customer_id' => $customer->id,
        'pricing_tier_id' => $tier->id,
        'unit_price_tzs' => 18,
    ]);

    $this->actingAs($admin)
        ->postJson("/api/admin/customers/{$customer->id}/credits", [
            'quantity' => 1000,
            'payment_method' => PaymentMethod::ManualMobile->value,
            'transaction_id' => 'MM-22',
        ])
        ->assertCreated()
        ->assertJsonPath('transaction.customerUnitPrice', 18)
        ->assertJsonPath('transaction.amountTzs', 18000);

    $payment = Payment::query()->where('customer_id', $customer->id)->first();

    expect($payment?->list_unit_price_tzs)->toBe(23)
        ->and($payment?->unit_price_tzs)->toBe(18);

    $this->actingAs($user)
        ->getJson("/api/payments/{$payment->id}/receipt")
        ->assertOk()
        ->assertJsonPath('receipt.line.unitPriceTzs', 18)
        ->assertJsonPath('receipt.line.listUnitPriceTzs', 23)
        ->assertJsonPath('receipt.line.specialPrice', true)
        ->assertJsonPath('receipt.line.amountTzs', 18000)
        ->assertJsonPath('receipt.transactionId', 'MM-22')
        ->assertJsonPath('receipt.paymentMethodLabel', 'Mobile money');
});

it('still issues a receipt for an older admin grant that has no payment row', function () {
    [$user, $customer] = billingCustomer();
    $transaction = CreditTransaction::query()->create([
        'customer_id' => $customer->id,
        'type' => CreditTransactionType::Grant,
        'quantity' => 50,
        'customer_unit_price' => 25,
        'amount_tzs' => 1250,
        'balance_after' => 59,
        'reference' => 'GNT-LEGACY',
        'description' => 'Counter sale',
        'created_at' => now(),
    ]);

    $this->actingAs($user)
        ->getJson("/api/credit-transactions/{$transaction->id}/receipt")
        ->assertOk()
        ->assertJsonPath('receipt.receiptNumber', 'GNT-LEGACY')
        ->assertJsonPath('receipt.paymentMethod', 'admin_grant')
        ->assertJsonPath('receipt.paymentMethodLabel', 'Grant')
        ->assertJsonPath('receipt.transactionId', null)
        ->assertJsonPath('receipt.note', 'Counter sale')
        ->assertJsonPath('receipt.line.amountTzs', 1250);

    $welcome = app(CreditLedger::class)->grantWelcome($customer);

    $this->actingAs($user)
        ->getJson("/api/credit-transactions/{$welcome->id}/receipt")
        ->assertNotFound();
});

it('labels a manual grant as a purchase or a bonus and hides the admin wording', function () {
    $admin = User::factory()->admin()->create();
    [$user, $customer] = billingCustomer();

    $this->actingAs($admin)
        ->postJson("/api/admin/customers/{$customer->id}/credits", [
            'quantity' => 100,
            'kind' => 'purchase',
        ])
        ->assertCreated()
        ->assertJsonPath('transaction.type', 'purchase')
        ->assertJsonPath('transaction.description', 'Purchase of 100 SMS');

    $this->actingAs($admin)
        ->postJson("/api/admin/customers/{$customer->id}/credits", [
            'quantity' => 20,
            'kind' => 'bonus',
        ])
        ->assertCreated()
        ->assertJsonPath('transaction.type', 'grant')
        ->assertJsonPath('transaction.description', 'Grant of 20 SMS');

    CreditTransaction::query()->create([
        'customer_id' => $customer->id,
        'type' => CreditTransactionType::Grant,
        'quantity' => 10,
        'customer_unit_price' => 25,
        'amount_tzs' => 250,
        'balance_after' => $customer->fresh()->sms_balance,
        'reference' => 'GNT-OLD-WORDING',
        'description' => 'Admin grant of 10 SMS',
        'created_at' => now(),
    ]);

    $this->actingAs($user)
        ->getJson('/api/credit-transactions')
        ->assertOk()
        ->assertJsonPath('transactions.0.description', 'Grant of 10 SMS')
        ->assertJsonPath('transactions.1.type', 'grant')
        ->assertJsonPath('transactions.1.description', 'Grant of 20 SMS')
        ->assertJsonPath('transactions.2.type', 'purchase')
        ->assertJsonPath('transactions.2.description', 'Purchase of 100 SMS');
});
