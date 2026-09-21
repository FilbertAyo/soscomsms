<?php

use App\Enums\AuditAction;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\CustomerStatus;
use App\Enums\DeliveryStatus;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Message;
use App\Models\Provider;
use App\Models\ProviderLog;
use App\Models\SenderId;
use App\Models\User;
use App\Services\CreditLedger;
use Database\Seeders\PricingTierSeeder;

function adminDashboardCustomer(): array
{
    $admin = User::factory()->admin()->create([
        'name' => 'Soscom Admin',
        'email' => 'admin@soscom.co.tz',
    ]);
    $user = User::factory()->create([
        'name' => 'Amina Mwamba',
        'email' => 'customer@soscom.co.tz',
    ]);
    $customer = Customer::factory()->complete()->create([
        'user_id' => $user->id,
        'business_name' => 'Linkadi Ltd',
        'phone' => '+255712445221',
        'sms_balance' => 20,
    ]);

    return [$admin, $user, $customer];
}

beforeEach(function () {
    $this->seed(PricingTierSeeder::class);
});

it('forbids customers from the admin overview', function () {
    [, $user] = adminDashboardCustomer();

    $this->actingAs($user)
        ->getJson('/api/admin/overview')
        ->assertForbidden();
});

it('returns live overview counts for customers, traffic, and purchase revenue', function () {
    [$admin, , $customer] = adminDashboardCustomer();
    $ledger = app(CreditLedger::class);
    $ledger->purchase($customer, 500, 30, 'PAY-1', 'Purchase of 500 SMS via Lipa namba');
    $ledger->debitUsage($customer->fresh(), 12, 'Campaign usage');

    $sender = SenderId::factory()->generic()->create(['name' => 'INFO']);
    $message = Message::factory()->create(['customer_id' => $customer->id]);
    Campaign::factory()->create([
        'customer_id' => $customer->id,
        'sender_id_id' => $sender->id,
        'message_id' => $message->id,
        'name' => 'School fee reminder',
        'sender_name' => 'INFO',
        'status' => CampaignStatus::Completed,
        'recipient_count' => 10,
        'total_segments' => 12,
        'failed_count' => 2,
    ]);

    $this->actingAs($admin)
        ->getJson('/api/admin/overview')
        ->assertOk()
        ->assertJsonPath('customers', 1)
        ->assertJsonPath('smsTraffic', 12)
        ->assertJsonPath('smsThisMonth', 12)
        ->assertJsonPath('failedDeliveries', 2)
        ->assertJsonPath('purchaseRevenueTzs', 15000)
        ->assertJsonPath('billedUsageTzs', 360)
        ->assertJsonPath('providerCostTzs', 168)
        ->assertJsonPath('estimatedMarginTzs', 192)
        ->assertJsonPath('recentCustomers.0.email', 'customer@soscom.co.tz');
});

it('searches customers and lets an admin view, suspend, and unsuspend them', function () {
    [$admin, , $customer] = adminDashboardCustomer();
    $otherUser = User::factory()->create([
        'name' => 'Joseph Mushi',
        'email' => 'joseph@example.com',
    ]);
    Customer::factory()->complete()->create([
        'user_id' => $otherUser->id,
        'business_name' => 'Mchango Group',
    ]);

    $this->actingAs($admin)
        ->getJson('/api/admin/customers?q=customer@soscom')
        ->assertOk()
        ->assertJsonCount(1, 'customers')
        ->assertJsonPath('customers.0.email', 'customer@soscom.co.tz')
        ->assertJsonPath('customers.0.businessName', 'Linkadi Ltd');

    $this->actingAs($admin)
        ->getJson("/api/admin/customers/{$customer->id}")
        ->assertOk()
        ->assertJsonPath('customer.phone', '+255712445221')
        ->assertJsonPath('customer.status', CustomerStatus::Active->value);

    $this->actingAs($admin)
        ->postJson("/api/admin/customers/{$customer->id}/suspension")
        ->assertOk()
        ->assertJsonPath('customer.status', CustomerStatus::Suspended->value);

    expect($customer->fresh()->status)->toBe(CustomerStatus::Suspended);

    $this->actingAs($admin)
        ->postJson("/api/admin/customers/{$customer->id}/unsuspension")
        ->assertOk()
        ->assertJsonPath('customer.status', CustomerStatus::Active->value);

    expect($customer->fresh()->status)->toBe(CustomerStatus::Active)
        ->and(AuditLog::query()->where('action', AuditAction::CustomerSuspended)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::CustomerUnsuspended)->count())->toBe(1);
});

it('lists live traffic and delivery reports for the demo customer', function () {
    [$admin, , $customer] = adminDashboardCustomer();
    $sender = SenderId::factory()->generic()->create(['name' => 'INFO']);
    $message = Message::factory()->create([
        'customer_id' => $customer->id,
        'body' => 'Habari',
    ]);
    $campaign = Campaign::factory()->create([
        'customer_id' => $customer->id,
        'sender_id_id' => $sender->id,
        'message_id' => $message->id,
        'name' => 'School fee reminder',
        'sender_name' => 'INFO',
        'status' => CampaignStatus::Completed,
        'recipient_count' => 1,
        'total_segments' => 1,
        'sent_count' => 1,
        'delivered_count' => 1,
    ]);
    $recipient = CampaignRecipient::factory()->create([
        'campaign_id' => $campaign->id,
        'message_id' => $message->id,
        'phone' => '+255712445221',
        'status' => CampaignRecipientStatus::Delivered,
        'segment_count' => 1,
    ]);
    Delivery::factory()->create([
        'campaign_recipient_id' => $recipient->id,
        'provider_id' => Provider::current()->id,
        'provider_message_id' => 'fake-1',
        'status' => DeliveryStatus::Delivered,
        'sent_at' => now(),
        'delivered_at' => now(),
    ]);

    $this->actingAs($admin)
        ->getJson('/api/admin/traffic')
        ->assertOk()
        ->assertJsonPath('smsTraffic', 1)
        ->assertJsonPath('campaigns.0.name', 'School fee reminder')
        ->assertJsonPath('campaigns.0.customerEmail', 'customer@soscom.co.tz')
        ->assertJsonPath('campaigns.0.totalSms', 1);

    $this->actingAs($admin)
        ->getJson('/api/admin/delivery-reports')
        ->assertOk()
        ->assertJsonPath('reports.0.phone', '+255712445221')
        ->assertJsonPath('reports.0.customerEmail', 'customer@soscom.co.tz')
        ->assertJsonPath('reports.0.providerMessageId', 'fake-1')
        ->assertJsonPath('reports.0.status', CampaignRecipientStatus::Delivered->value);
});

it('records grants in the audit log and shows provider logs without gateway payloads', function () {
    [$admin, , $customer] = adminDashboardCustomer();

    $this->actingAs($admin)
        ->postJson("/api/admin/customers/{$customer->id}/credits", [
            'quantity' => 1000,
            'note' => 'Starter top-up',
        ])
        ->assertCreated();

    ProviderLog::query()->create([
        'provider_id' => Provider::current()->id,
        'action' => 'send_bulk',
        'request_payload' => ['pwd' => 'secret', 'count' => 1],
        'response_payload' => ['accepted' => 1],
        'status' => 'ok',
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/logs')
        ->assertOk()
        ->assertJsonPath('auditLogs.0.action', AuditAction::CreditsGranted->value)
        ->assertJsonPath('providerLogs.0.action', 'send_bulk')
        ->assertJsonPath('providerLogs.0.status', 'ok')
        ->assertJsonPath('providerMeta.total', 1)
        ->assertJsonPath('auditMeta.total', 1);

    expect($response->json('providerLogs.0'))->not->toHaveKey('request_payload')
        ->and($response->json('providerLogs.0'))->not->toHaveKey('response_payload')
        ->and($response->json('providerLogs.0.diagnosis'))->not->toBeEmpty()
        ->and($response->json('auditLogs.0.message'))->toContain('1000 SMS')
        ->and($response->json('auditLogs.0.message'))->toContain('customer@soscom.co.tz');
});
