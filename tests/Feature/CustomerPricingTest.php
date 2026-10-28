<?php

use App\Enums\AuditAction;
use App\Enums\CreditTransactionType;
use App\Enums\PaymentStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerPrice;
use App\Models\Payment;
use App\Models\PricingTier;
use App\Models\User;
use Database\Seeders\PricingTierSeeder;
use Illuminate\Support\Facades\Http;

function pricingCustomer(int $balance = 9): array
{
    $user = User::factory()->create();
    $customer = Customer::factory()->complete()->create([
        'user_id' => $user->id,
        'sms_balance' => $balance,
    ]);

    return [$user, $customer];
}

function packageTier(int $minQuantity): PricingTier
{
    $tier = PricingTier::query()->where('min_quantity', $minQuantity)->first();
    expect($tier)->not->toBeNull();

    return $tier;
}

beforeEach(function () {
    $this->seed(PricingTierSeeder::class);
    configureSnippe();
    fakeSnippePayment();
});

it('keeps the normal package price for a customer without a special price', function () {
    [$user] = pricingCustomer();

    $this->actingAs($user)
        ->postJson('/api/quotes', ['quantity' => 1000])
        ->assertOk()
        ->assertJsonPath('quote.unitPriceTzs', 23)
        ->assertJsonPath('quote.listUnitPriceTzs', 23)
        ->assertJsonPath('quote.specialPrice', false)
        ->assertJsonPath('quote.totalTzs', 23000);

    $this->actingAs($user)
        ->postJson('/api/payments', [
            'quantity' => 1000,
            'payer_reference' => '0754221890',
        ])
        ->assertCreated()
        ->assertJsonPath('payment.unitPriceTzs', 23)
        ->assertJsonPath('payment.listUnitPriceTzs', 23)
        ->assertJsonPath('payment.specialPrice', false)
        ->assertJsonPath('payment.amountTzs', 23000)
        ->assertJsonPath('smsBalance', 9);

    Http::assertSent(fn ($request) => $request['details']['amount'] === 23000);
});

it('charges a negotiated customer their agreed package price and leaves the public price unchanged', function () {
    $admin = User::factory()->admin()->create(['name' => 'Neema Admin']);
    [$user, $customer] = pricingCustomer();
    [$otherUser] = pricingCustomer();
    $tier = packageTier(1000);

    $this->actingAs($admin)
        ->postJson("/api/admin/customers/{$customer->id}/prices", [
            'pricing_tier_id' => $tier->id,
            'unit_price_tzs' => 18,
        ])
        ->assertOk()
        ->assertJsonPath('package.listUnitPriceTzs', 23)
        ->assertJsonPath('package.price.unitPriceTzs', 18)
        ->assertJsonPath('package.price.active', true);

    expect($tier->fresh()->unit_price_tzs)->toBe(23);

    $this->actingAs($user)
        ->getJson('/api/wallet')
        ->assertOk()
        ->assertJsonPath('tiers.1.unitPriceTzs', 18)
        ->assertJsonPath('tiers.1.listUnitPriceTzs', 23)
        ->assertJsonPath('tiers.1.specialPrice', true)
        ->assertJsonPath('tiers.0.unitPriceTzs', 25)
        ->assertJsonPath('tiers.0.specialPrice', false)
        ->assertJsonPath('packages.1.totalTzs', 18000);

    $this->actingAs($user)
        ->postJson('/api/quotes', [
            'quantity' => 1000,
            'unit_price_tzs' => 1,
            'amount_tzs' => 1,
            'discount' => 17000,
        ])
        ->assertOk()
        ->assertJsonPath('quote.unitPriceTzs', 18)
        ->assertJsonPath('quote.totalTzs', 18000)
        ->assertJsonPath('quote.specialPrice', true);

    $this->actingAs($user)
        ->postJson('/api/quotes', ['quantity' => 2000])
        ->assertOk()
        ->assertJsonPath('quote.unitPriceTzs', 18)
        ->assertJsonPath('quote.totalTzs', 36000);

    $this->actingAs($user)
        ->postJson('/api/quotes', ['quantity' => 100])
        ->assertOk()
        ->assertJsonPath('quote.unitPriceTzs', 25)
        ->assertJsonPath('quote.specialPrice', false);

    $create = $this->actingAs($user)
        ->postJson('/api/payments', [
            'quantity' => 1000,
            'payer_reference' => '0754221890',
            'unit_price_tzs' => 1,
            'unitPriceTzs' => 1,
            'amount_tzs' => 1,
            'amountTzs' => 1,
            'discount' => 17000,
            'total' => 1,
            'pricing_tier_id' => packageTier(5000)->id,
        ])
        ->assertCreated()
        ->assertJsonPath('payment.quantity', 1000)
        ->assertJsonPath('payment.unitPriceTzs', 18)
        ->assertJsonPath('payment.listUnitPriceTzs', 23)
        ->assertJsonPath('payment.amountTzs', 18000)
        ->assertJsonPath('smsBalance', 9);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.snippe.sh/v1/payments'
            && $request['details']['amount'] === 18000
            && $request['details']['currency'] === 'TZS';
    });

    expect($customer->fresh()->sms_balance)->toBe(9)
        ->and($customer->creditTransactions()->where('type', CreditTransactionType::Purchase)->count())->toBe(0);

    $this->actingAs($otherUser)
        ->postJson('/api/quotes', ['quantity' => 1000])
        ->assertJsonPath('quote.unitPriceTzs', 23)
        ->assertJsonPath('quote.specialPrice', false);

    $log = AuditLog::query()->where('action', AuditAction::CustomerPriceSaved)->first();
    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBe($admin->id)
        ->and($log->customer_id)->toBe($customer->id)
        ->and($log->payload['to'])->toBe(18)
        ->and($log->payload['list_unit_price_tzs'])->toBe(23)
        ->and($log->toAdminArray()['message'])->toContain('TZS 18');

    expect((int) $create->json('payment.id'))->toBeGreaterThan(0);
});

it('keeps the charged price after the public price and the special price change', function () {
    $admin = User::factory()->admin()->create();
    [$user, $customer] = pricingCustomer();
    $tier = packageTier(1000);

    $this->actingAs($admin)
        ->postJson("/api/admin/customers/{$customer->id}/prices", [
            'pricing_tier_id' => $tier->id,
            'unit_price_tzs' => 18,
        ])
        ->assertOk();

    $paymentId = (int) $this->actingAs($user)
        ->postJson('/api/payments', [
            'quantity' => 1000,
            'payer_reference' => '0754221890',
        ])
        ->assertCreated()
        ->json('payment.id');

    postSnippeWebhook(snippeCompletedEvent('ref-pay-1', 18000, $paymentId))
        ->assertOk();

    $this->actingAs($admin)
        ->postJson("/api/admin/customers/{$customer->id}/prices", [
            'pricing_tier_id' => $tier->id,
            'unit_price_tzs' => 19,
        ])
        ->assertOk();
    $tier->update(['unit_price_tzs' => 25]);

    $payment = Payment::query()->findOrFail($paymentId);
    $purchase = $customer->creditTransactions()
        ->where('type', CreditTransactionType::Purchase)
        ->first();

    expect($customer->fresh()->sms_balance)->toBe(1009)
        ->and($payment->quantity)->toBe(1000)
        ->and($payment->unit_price_tzs)->toBe(18)
        ->and($payment->list_unit_price_tzs)->toBe(23)
        ->and($payment->amount_tzs)->toBe(18000)
        ->and($payment->status)->toBe(PaymentStatus::Paid)
        ->and($purchase)->not->toBeNull()
        ->and($purchase->quantity)->toBe(1000)
        ->and($purchase->customer_unit_price)->toBe(18)
        ->and($purchase->amount_tzs)->toBe(18000)
        ->and($purchase->description)->toContain('TZS 18')
        ->and($purchase->description)->toContain('TZS 23');

    $this->actingAs($user)
        ->postJson('/api/quotes', ['quantity' => 1000])
        ->assertOk()
        ->assertJsonPath('quote.unitPriceTzs', 19)
        ->assertJsonPath('quote.listUnitPriceTzs', 25)
        ->assertJsonPath('quote.totalTzs', 19000);

    expect($payment->fresh()->amount_tzs)->toBe(18000)
        ->and($purchase->fresh()->amount_tzs)->toBe(18000)
        ->and($purchase->fresh()->customer_unit_price)->toBe(18);
});

it('rejects a snippe amount that does not match the stored special price and does not credit SMS', function () {
    $admin = User::factory()->admin()->create();
    [$user, $customer] = pricingCustomer();
    $tier = packageTier(1000);

    $this->actingAs($admin)
        ->postJson("/api/admin/customers/{$customer->id}/prices", [
            'pricing_tier_id' => $tier->id,
            'unit_price_tzs' => 18,
        ])
        ->assertOk();

    $paymentId = (int) $this->actingAs($user)
        ->postJson('/api/payments', [
            'quantity' => 1000,
            'payer_reference' => '0754221890',
        ])
        ->assertCreated()
        ->json('payment.id');

    $this->actingAs($admin)
        ->postJson("/api/admin/customers/{$customer->id}/prices", [
            'pricing_tier_id' => $tier->id,
            'unit_price_tzs' => 19,
        ])
        ->assertOk();

    postSnippeWebhook(snippeCompletedEvent('ref-pay-1', 19000, $paymentId))
        ->assertOk();

    $payment = Payment::query()->findOrFail($paymentId);

    expect($customer->fresh()->sms_balance)->toBe(9)
        ->and($payment->status)->toBe(PaymentStatus::Failed)
        ->and($payment->unit_price_tzs)->toBe(18)
        ->and($payment->list_unit_price_tzs)->toBe(23)
        ->and($payment->amount_tzs)->toBe(18000)
        ->and($customer->creditTransactions()->where('type', CreditTransactionType::Purchase)->count())->toBe(0);
});

it('stops using a special price after it is removed or expires', function () {
    $admin = User::factory()->admin()->create();
    [$user, $customer] = pricingCustomer();
    $tier = packageTier(1000);

    $created = $this->actingAs($admin)
        ->postJson("/api/admin/customers/{$customer->id}/prices", [
            'pricing_tier_id' => $tier->id,
            'unit_price_tzs' => 18,
            'expires_at' => now()->addDay()->toDateString(),
        ])
        ->assertOk()
        ->json('package.price');

    $this->actingAs($user)
        ->postJson('/api/quotes', ['quantity' => 1000])
        ->assertJsonPath('quote.unitPriceTzs', 18);

    $this->travel(2)->days();

    $this->actingAs($user)
        ->postJson('/api/quotes', ['quantity' => 1000])
        ->assertJsonPath('quote.unitPriceTzs', 23)
        ->assertJsonPath('quote.specialPrice', false);

    $this->travelBack();

    CustomerPrice::query()->whereKey($created['id'])->update([
        'expires_at' => now()->addDay(),
        'disabled_at' => null,
    ]);

    $this->actingAs($admin)
        ->deleteJson("/api/admin/customers/{$customer->id}/prices/{$created['id']}")
        ->assertOk()
        ->assertJsonPath('removed', true);

    $this->actingAs($user)
        ->postJson('/api/quotes', ['quantity' => 1000])
        ->assertJsonPath('quote.unitPriceTzs', 23)
        ->assertJsonPath('quote.specialPrice', false);

    $removed = AuditLog::query()->where('action', AuditAction::CustomerPriceRemoved)->first();
    expect($removed)->not->toBeNull()
        ->and($removed->actor_id)->toBe($admin->id)
        ->and($removed->toAdminArray()['level'])->toBe('warning');

    $this->actingAs($admin)
        ->getJson("/api/admin/customers/{$customer->id}/prices")
        ->assertOk()
        ->assertJsonPath('packages.1.price', null)
        ->assertJsonPath('packages.1.listUnitPriceTzs', 23);

    $this->actingAs($admin)
        ->postJson("/api/admin/customers/{$customer->id}/prices", [
            'pricing_tier_id' => $tier->id,
            'unit_price_tzs' => 18,
        ])
        ->assertOk()
        ->assertJsonPath('package.price.active', true);

    $this->actingAs($user)
        ->postJson('/api/quotes', ['quantity' => 1000])
        ->assertJsonPath('quote.unitPriceTzs', 18)
        ->assertJsonPath('quote.specialPrice', true);
});

it('does not credit SMS when a special-price payment fails or the webhook is repeated', function () {
    $admin = User::factory()->admin()->create();
    [$user, $customer] = pricingCustomer();
    $tier = packageTier(1000);

    $this->actingAs($admin)
        ->postJson("/api/admin/customers/{$customer->id}/prices", [
            'pricing_tier_id' => $tier->id,
            'unit_price_tzs' => 18,
        ])
        ->assertOk();

    $failedId = (int) $this->actingAs($user)
        ->postJson('/api/payments', [
            'quantity' => 1000,
            'payer_reference' => '0754221890',
        ])
        ->assertCreated()
        ->json('payment.id');

    postSnippeWebhook([
        'id' => 'evt_special_failed',
        'type' => 'payment.failed',
        'data' => [
            'reference' => 'ref-pay-1',
            'status' => 'failed',
            'amount' => ['value' => 18000, 'currency' => 'TZS'],
            'metadata' => ['payment_id' => (string) $failedId],
        ],
    ])->assertOk();

    expect($customer->fresh()->sms_balance)->toBe(9)
        ->and(Payment::query()->find($failedId)?->status)->toBe(PaymentStatus::Failed)
        ->and($customer->creditTransactions()->where('type', CreditTransactionType::Purchase)->count())->toBe(0);

    stubSnippe([
        'https://api.snippe.sh/v1/payments' => Http::response([
            'status' => 'success',
            'code' => 201,
            'data' => [
                'reference' => 'ref-pay-once',
                'status' => 'pending',
            ],
        ], 201),
    ]);

    $paidId = (int) $this->actingAs($user)
        ->postJson('/api/payments', [
            'quantity' => 1000,
            'payer_reference' => '0754221890',
        ])
        ->assertCreated()
        ->json('payment.id');

    $event = snippeCompletedEvent('ref-pay-once', 18000, $paidId);
    postSnippeWebhook($event)->assertOk();
    postSnippeWebhook($event)->assertOk();

    expect($customer->fresh()->sms_balance)->toBe(1009)
        ->and($customer->creditTransactions()->where('type', CreditTransactionType::Purchase)->count())->toBe(1);

    postSnippeWebhook(snippeCompletedEvent('ref-pay-once', 1, $paidId, [
        'id' => 'evt_late_low_amount',
    ]))->assertOk();

    expect($customer->fresh()->sms_balance)->toBe(1009);
});

it('rejects special prices from customers and invalid admin prices', function () {
    $admin = User::factory()->admin()->create();
    [$user, $customer] = pricingCustomer();
    [$otherUser, $other] = pricingCustomer();
    $tier = packageTier(1000);

    $this->postJson("/api/admin/customers/{$customer->id}/prices", [
        'pricing_tier_id' => $tier->id,
        'unit_price_tzs' => 18,
    ])->assertUnauthorized();

    $this->actingAs($user)
        ->postJson("/api/admin/customers/{$customer->id}/prices", [
            'pricing_tier_id' => $tier->id,
            'unit_price_tzs' => 1,
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->postJson("/api/admin/customers/{$customer->id}/prices", [
            'pricing_tier_id' => $tier->id,
            'unit_price_tzs' => 0,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('unit_price_tzs');

    $this->actingAs($admin)
        ->postJson("/api/admin/customers/{$customer->id}/prices", [
            'pricing_tier_id' => 999999,
            'unit_price_tzs' => 18,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('pricing_tier_id');

    $this->actingAs($admin)
        ->postJson("/api/admin/customers/{$customer->id}/prices", [
            'pricing_tier_id' => $tier->id,
            'unit_price_tzs' => 18,
            'expires_at' => now()->subDay()->toDateString(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('expires_at');

    $priceId = (int) $this->actingAs($admin)
        ->postJson("/api/admin/customers/{$customer->id}/prices", [
            'pricing_tier_id' => $tier->id,
            'unit_price_tzs' => 18,
        ])
        ->assertOk()
        ->json('package.price.id');

    $this->actingAs($admin)
        ->deleteJson("/api/admin/customers/{$other->id}/prices/{$priceId}")
        ->assertNotFound();

    expect(CustomerPrice::query()->find($priceId)?->disabled_at)->toBeNull();

    $this->actingAs($otherUser)
        ->postJson('/api/quotes', ['quantity' => 1000])
        ->assertJsonPath('quote.unitPriceTzs', 23);

    $this->actingAs($user)
        ->postJson('/api/quotes', [
            'quantity' => 1000,
            'customer_id' => $other->id,
            'unitPriceTzs' => 1,
        ])
        ->assertJsonPath('quote.unitPriceTzs', 18);
});

it('does not apply a special price before its start date', function () {
    [$user, $customer] = pricingCustomer();
    $tier = packageTier(1000);

    CustomerPrice::query()->create([
        'customer_id' => $customer->id,
        'pricing_tier_id' => $tier->id,
        'unit_price_tzs' => 18,
        'starts_at' => now()->addDay(),
    ]);

    $this->actingAs($user)
        ->postJson('/api/quotes', ['quantity' => 1000])
        ->assertJsonPath('quote.unitPriceTzs', 23)
        ->assertJsonPath('quote.specialPrice', false);

    $this->travel(2)->days();

    $this->actingAs($user)
        ->postJson('/api/quotes', ['quantity' => 1000])
        ->assertJsonPath('quote.unitPriceTzs', 18)
        ->assertJsonPath('quote.specialPrice', true);

    $this->travelBack();
});
