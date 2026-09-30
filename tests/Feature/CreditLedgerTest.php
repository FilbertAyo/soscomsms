<?php

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\Customer;
use App\Models\User;
use App\Services\CreditLedger;
use Database\Seeders\PricingTierSeeder;

beforeEach(function () {
    $this->seed(PricingTierSeeder::class);
});

it('does not debit the same campaign usage reference twice', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->complete()->create([
        'user_id' => $user->id,
        'sms_balance' => 20,
    ]);
    $ledger = app(CreditLedger::class);

    $first = $ledger->debitUsage($customer, 2, 'Campaign send', 'USE-CAMP-99');
    $second = $ledger->debitUsage($customer->fresh(), 2, 'Campaign send', 'USE-CAMP-99');

    expect($first->id)->toBe($second->id)
        ->and($customer->fresh()->sms_balance)->toBe(18)
        ->and(CreditTransaction::query()->where('type', CreditTransactionType::Usage)->count())->toBe(1);
});

it('adds two grants onto the cached balance and the ledger', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->complete()->create(['user_id' => $user->id]);
    $ledger = app(CreditLedger::class);

    $ledger->grant($customer, 20, 'Welcome');
    $ledger->grant($customer->fresh(), 1000, 'Admin top-up');

    $customer->refresh();

    expect($customer->sms_balance)->toBe(1020)
        ->and($customer->creditTransactions()->count())->toBe(2)
        ->and($customer->creditTransactions()->latest('id')->value('balance_after'))->toBe(1020);
});

it('creates a reversing row without editing the original grant', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->complete()->create(['user_id' => $user->id]);
    $ledger = app(CreditLedger::class);
    $grant = $ledger->grant($customer, 1000);

    $reversal = $ledger->reverse($grant);

    expect($grant->fresh()->quantity)->toBe(1000)
        ->and($grant->fresh()->description)->toBe($grant->description)
        ->and($reversal->type)->toBe(CreditTransactionType::Reversal)
        ->and($reversal->quantity)->toBe(-1000)
        ->and($reversal->related_transaction_id)->toBe($grant->id)
        ->and($customer->fresh()->sms_balance)->toBe(0)
        ->and(CreditTransaction::query()->count())->toBe(2);
});

it('quotes the matching volume tier', function () {
    $user = User::factory()->create();
    Customer::factory()->complete()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson('/api/quotes', ['quantity' => 10000])
        ->assertOk()
        ->assertJsonPath('quote.quantity', 10000)
        ->assertJsonPath('quote.unitPriceTzs', 20)
        ->assertJsonPath('quote.totalTzs', 200000);

    $this->actingAs($user)
        ->postJson('/api/quotes', ['quantity' => 500])
        ->assertOk()
        ->assertJsonPath('quote.unitPriceTzs', 25)
        ->assertJsonPath('quote.totalTzs', 12500);

    $this->actingAs($user)
        ->postJson('/api/quotes', ['quantity' => 100])
        ->assertOk()
        ->assertJsonPath('quote.unitPriceTzs', 25)
        ->assertJsonPath('quote.totalTzs', 2500);

    $this->actingAs($user)
        ->postJson('/api/quotes', ['quantity' => 499])
        ->assertOk()
        ->assertJsonPath('quote.unitPriceTzs', 25)
        ->assertJsonPath('quote.totalTzs', 12475);
});

it('rejects quotes below the minimum package', function () {
    $user = User::factory()->create();
    Customer::factory()->complete()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson('/api/quotes', ['quantity' => 20])
        ->assertUnprocessable();
});

it('lets an admin grant credits and the customer wallet shows them', function () {
    $admin = User::factory()->admin()->create();
    $customerUser = User::factory()->create();
    $customer = Customer::factory()->complete()->create(['user_id' => $customerUser->id]);

    $this->actingAs($admin)
        ->postJson("/api/admin/customers/{$customer->id}/credits", [
            'quantity' => 1000,
            'note' => 'Starter top-up',
        ])
        ->assertCreated()
        ->assertJsonPath('smsBalance', 1000)
        ->assertJsonPath('transaction.customerUnitPrice', 23)
        ->assertJsonPath('transaction.amountTzs', 23000);

    $this->actingAs($admin)
        ->getJson('/api/admin/overview')
        ->assertOk()
        ->assertJsonPath('purchaseRevenueTzs', 23000);

    $this->actingAs($customerUser)
        ->getJson('/api/wallet')
        ->assertOk()
        ->assertJsonPath('smsBalance', 1000)
        ->assertJsonPath('minPurchaseQuantity', 100)
        ->assertJsonPath('tiers.0.minQuantity', 100)
        ->assertJsonPath('tiers.0.unitPriceTzs', 25);
});

it('lets an admin override the grant price for a preferred customer', function () {
    $admin = User::factory()->admin()->create();
    $customerUser = User::factory()->create();
    $customer = Customer::factory()->complete()->create(['user_id' => $customerUser->id]);

    $this->actingAs($admin)
        ->postJson("/api/admin/customers/{$customer->id}/credits", [
            'quantity' => 1000,
            'amount_tzs' => 20000,
            'unit_price_tzs' => 20,
            'note' => 'Preferred customer',
        ])
        ->assertCreated()
        ->assertJsonPath('transaction.customerUnitPrice', 20)
        ->assertJsonPath('transaction.amountTzs', 20000);

    $this->actingAs($admin)
        ->getJson('/api/admin/overview')
        ->assertOk()
        ->assertJsonPath('purchaseRevenueTzs', 20000);
});

it('prices grants below the first published range at the first-range unit price', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->complete()->create(['user_id' => $user->id]);
    $grant = app(CreditLedger::class)->grant($customer, 20, 'Small grant');

    expect($grant->customer_unit_price)->toBe(25)
        ->and($grant->amount_tzs)->toBe(500);
});

it('lets an admin reverse a grant and keeps both ledger rows', function () {
    $admin = User::factory()->admin()->create();
    $customerUser = User::factory()->create();
    $customer = Customer::factory()->complete()->create(['user_id' => $customerUser->id]);
    $grant = app(CreditLedger::class)->grant($customer, 1000);

    $this->actingAs($admin)
        ->postJson("/api/admin/credit-transactions/{$grant->id}/reversals")
        ->assertCreated()
        ->assertJsonPath('transaction.quantity', -1000);

    expect($grant->fresh()->quantity)->toBe(1000)
        ->and($customer->fresh()->sms_balance)->toBe(0)
        ->and(CreditTransaction::query()->count())->toBe(2);
});

it('updates a pricing tier for future quotes only', function () {
    $admin = User::factory()->admin()->create();
    $customerUser = User::factory()->create();
    Customer::factory()->complete()->create(['user_id' => $customerUser->id]);

    $this->actingAs($customerUser)
        ->postJson('/api/quotes', ['quantity' => 50000])
        ->assertJsonPath('quote.unitPriceTzs', 20);

    $tierId = $this->actingAs($admin)
        ->getJson('/api/admin/pricing-tiers')
        ->assertOk()
        ->json('tiers.2.id');

    $this->actingAs($admin)
        ->patchJson("/api/admin/pricing-tiers/{$tierId}", [
            'unit_price_tzs' => 19,
        ])
        ->assertOk()
        ->assertJsonPath('tier.unitPriceTzs', 19);

    $this->actingAs($customerUser)
        ->postJson('/api/quotes', ['quantity' => 50000])
        ->assertJsonPath('quote.unitPriceTzs', 19)
        ->assertJsonPath('quote.totalTzs', 950000);
});

it('forbids customers from granting credits', function () {
    $customerUser = User::factory()->create();
    $customer = Customer::factory()->complete()->create(['user_id' => $customerUser->id]);

    $this->actingAs($customerUser)
        ->postJson("/api/admin/customers/{$customer->id}/credits", [
            'quantity' => 1000,
        ])
        ->assertForbidden();
});

it('values the signup bonus at the first-range price and hides SMS usage from the customer ledger', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->complete()->create([
        'user_id' => $user->id,
        'sms_balance' => 0,
    ]);
    $ledger = app(CreditLedger::class);

    $welcome = $ledger->grantWelcome($customer);
    $ledger->debitUsage($customer->fresh(), 2, 'Campaign send');
    $ledger->purchase($customer->fresh(), 300, 30, 'PAY-300', 'Purchase of 300 SMS via Lipa namba');

    expect($welcome->quantity)->toBe(20)
        ->and($welcome->amount_tzs)->toBe(500);

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)
        ->getJson('/api/admin/overview')
        ->assertOk()
        ->assertJsonPath('purchaseRevenueTzs', 9000);

    $this->actingAs($user)
        ->getJson('/api/credit-transactions')
        ->assertOk()
        ->assertJsonCount(2, 'transactions')
        ->assertJsonPath('transactions.0.quantity', 300)
        ->assertJsonPath('transactions.0.amountTzs', 9000)
        ->assertJsonPath('transactions.1.quantity', 20)
        ->assertJsonPath('transactions.1.amountTzs', 500);

    $this->actingAs($user)
        ->getJson('/api/wallet')
        ->assertOk()
        ->assertJsonCount(2, 'recentTransactions')
        ->assertJsonMissing(['type' => 'usage']);
});

it('shows admin grants in the customer wallet, transactions, and dashboard feed', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->complete()->create([
        'user_id' => $user->id,
        'sms_balance' => 0,
    ]);
    $ledger = app(CreditLedger::class);

    $ledger->grantWelcome($customer);
    $ledger->grant($customer->fresh(), 800, 'Admin grant of 800 SMS');
    $ledger->debitUsage($customer->fresh(), 5, 'Campaign send');

    $this->actingAs($user)
        ->getJson('/api/credit-transactions')
        ->assertOk()
        ->assertJsonPath('smsBalance', 815)
        ->assertJsonCount(2, 'transactions')
        ->assertJsonPath('transactions.0.type', 'grant')
        ->assertJsonPath('transactions.0.quantity', 800)
        ->assertJsonPath('transactions.1.quantity', 20);

    $this->actingAs($user)
        ->getJson('/api/wallet')
        ->assertOk()
        ->assertJsonCount(2, 'recentTransactions')
        ->assertJsonPath('recentTransactions.0.quantity', 800)
        ->assertJsonMissing(['type' => 'usage']);
});
