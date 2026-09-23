<?php

use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\CreditTransactionType;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\CreditTransaction;
use App\Models\Customer;
use App\Models\SenderId;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function campaignCustomer(int $balance = 20): array
{
    $user = User::factory()->create();
    $customer = Customer::factory()->complete()->create([
        'user_id' => $user->id,
        'sms_balance' => $balance,
    ]);
    $sender = SenderId::factory()->generic()->create([
        'name' => strtoupper(fake()->unique()->lexify('??????')),
    ]);

    return [$user, $customer, $sender];
}

beforeEach(function () {
    Http::preventStrayRequests();
});

it('previews cost by segment count and queues a send that deducts credits', function () {
    [$user, $customer, $sender] = campaignCustomer(20);

    $this->actingAs($user)
        ->postJson('/api/sms/preview', [
            'source' => 'numbers',
            'message' => str_repeat('a', 161),
            'sender_id' => $sender->name,
            'numbers' => ['0712 445 221', '+255754221891'],
        ])
        ->assertOk()
        ->assertJsonPath('encoding', 'gsm')
        ->assertJsonPath('segmentsPerRecipient', 2)
        ->assertJsonPath('recipientCount', 2)
        ->assertJsonPath('totalSms', 4)
        ->assertJsonPath('unitPriceTzs', 30)
        ->assertJsonPath('costTzs', 120)
        ->assertJsonPath('enoughCredits', true);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => str_repeat('a', 161),
            'sender_id' => $sender->name,
            'numbers' => ['0712 445 221', '+255754221891'],
            'name' => 'Fee reminder',
        ])
        ->assertCreated()
        ->assertJsonPath('campaign.status', CampaignStatus::Completed->value)
        ->assertJsonPath('campaign.recipients', 2)
        ->assertJsonPath('campaign.sent', 2)
        ->assertJsonPath('campaign.delivered', 2)
        ->assertJsonPath('campaign.totalSms', 4)
        ->assertJsonPath('smsBalance', 16);

    $campaign = Campaign::query()->first();

    expect($customer->fresh()->sms_balance)->toBe(16)
        ->and($campaign?->queued_at)->not->toBeNull()
        ->and($campaign?->credit_transaction_id)->not->toBeNull();

    $usage = CreditTransaction::query()->find($campaign->credit_transaction_id);

    expect($usage?->type)->toBe(CreditTransactionType::Usage)
        ->and($usage?->quantity)->toBe(-4)
        ->and($usage?->customer_unit_price)->toBe(30)
        ->and($usage?->provider_unit_cost)->toBe(14)
        ->and($usage?->amount_tzs)->toBe(120)
        ->and($usage?->reference)->toBe('USE-CAMP-'.$campaign->id)
        ->and($usage?->description)->toContain('Reserved for campaign');

    $this->assertDatabaseCount('campaign_recipients', 2);
    $this->assertDatabaseHas('campaign_recipients', [
        'campaign_id' => $campaign->id,
        'phone' => '+255712445221',
        'status' => CampaignRecipientStatus::Delivered->value,
        'segment_count' => 2,
    ]);
    $this->assertDatabaseHas('deliveries', [
        'provider_message_id' => 'FAKE-R'.$campaign->recipients()->where('phone', '+255712445221')->value('id'),
        'status' => 'delivered',
    ]);
    expect(CreditTransaction::query()->where('type', CreditTransactionType::Usage)->count())->toBe(1);
});

it('lists the recipient phone and completes after the provider accepts the send', function () {
    [$user, , $sender] = campaignCustomer(20);
    config(['sms.fake.auto_dlr' => false]);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $sender->name,
            'numbers' => ['0755237692'],
            'name' => 'One number',
        ])
        ->assertCreated()
        ->assertJsonPath('campaign.status', CampaignStatus::Completed->value)
        ->assertJsonPath('campaign.recipientPhone', '+255755237692')
        ->assertJsonPath('campaign.sent', 1)
        ->assertJsonPath('campaign.delivered', 0);

    $this->actingAs($user)
        ->getJson('/api/campaigns?status=completed')
        ->assertOk()
        ->assertJsonPath('campaigns.0.recipientPhone', '+255755237692')
        ->assertJsonPath('campaigns.0.status', CampaignStatus::Completed->value);

    $this->actingAs($user)
        ->getJson('/api/delivery-reports')
        ->assertOk()
        ->assertJsonPath('reports.0.phone', '+255755237692')
        ->assertJsonPath('reports.0.status', CampaignRecipientStatus::Sent->value)
        ->assertJsonPath('reports.0.campaignName', 'One number');
});

it('rejects a send when the customer does not have enough credits', function () {
    [$user, $customer, $sender] = campaignCustomer(1);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $sender->name,
            'numbers' => ['0712445221', '0754221891'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('quantity');

    expect($customer->fresh()->sms_balance)->toBe(1);
    $this->assertDatabaseCount('campaigns', 0);
    $this->assertDatabaseCount('credit_transactions', 0);
});

it('skips invalid numbers, charges unique valid numbers once, and does not store invalid rows', function () {
    [$user, $customer, $sender] = campaignCustomer(20);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $sender->name,
            'numbers' => [
                '0712 445 221',
                '+255712445221',
                '+254712345678',
                'not-a-phone',
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('campaign.recipients', 1)
        ->assertJsonPath('campaign.totalSms', 1)
        ->assertJsonPath('smsBalance', 19);

    $this->assertDatabaseCount('campaign_recipients', 1);
    $this->assertDatabaseHas('campaign_recipients', [
        'phone' => '+255712445221',
    ]);
    $this->assertDatabaseMissing('campaign_recipients', [
        'phone' => '+254712345678',
    ]);
    expect($customer->fresh()->sms_balance)->toBe(19);
});

it('sends to a group but excludes inactive contacts', function () {
    [$user, $customer, $sender] = campaignCustomer(20);
    $group = ContactGroup::factory()->create([
        'customer_id' => $customer->id,
        'name' => 'Parents',
    ]);
    $active = Contact::factory()->create([
        'customer_id' => $customer->id,
        'contact_group_id' => $group->id,
        'phone' => '+255712445221',
    ]);
    Contact::factory()->create([
        'customer_id' => $customer->id,
        'contact_group_id' => $group->id,
        'phone' => '+255754221891',
    ]);
    Contact::factory()->inactive()->create([
        'customer_id' => $customer->id,
        'contact_group_id' => $group->id,
        'phone' => '+255755000001',
    ]);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'group',
            'group_id' => $group->id,
            'message' => 'Habari wazazi',
            'sender_id' => $sender->name,
        ])
        ->assertCreated()
        ->assertJsonPath('campaign.recipients', 2)
        ->assertJsonPath('smsBalance', 18);

    $this->assertDatabaseMissing('campaign_recipients', [
        'phone' => '+255755000001',
    ]);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'contacts',
            'contact_ids' => [$active->id],
            'message' => 'Habari tena',
            'sender_id' => $sender->name,
        ])
        ->assertCreated()
        ->assertJsonPath('campaign.recipients', 1);
});

it('hides campaigns from other customers and blocks their sender ids', function () {
    [$owner, $ownerCustomer, $ownerSender] = campaignCustomer(20);
    [$stranger] = campaignCustomer(20);
    $owned = SenderId::factory()->create([
        'customer_id' => $ownerCustomer->id,
        'name' => 'LINKADI',
    ]);

    $this->actingAs($owner)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $ownerSender->name,
            'numbers' => ['0712445221'],
        ])
        ->assertCreated();

    $campaignId = Campaign::query()->value('id');

    $this->actingAs($stranger)
        ->getJson('/api/campaigns')
        ->assertOk()
        ->assertJsonCount(0, 'campaigns');

    $this->actingAs($stranger)
        ->getJson("/api/campaigns/{$campaignId}")
        ->assertNotFound();

    $this->actingAs($stranger)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $owned->name,
            'numbers' => ['0712445221'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sender_id');
});

it('does not deduct credits when the provider rejects every recipient', function () {
    [$user, $customer, $sender] = campaignCustomer(20);
    config(['sms.fake.fail_phones' => ['+255712445221']]);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $sender->name,
            'numbers' => ['0712445221'],
        ])
        ->assertCreated()
        ->assertJsonPath('campaign.status', CampaignStatus::Failed->value)
        ->assertJsonPath('smsBalance', 20);

    expect($customer->fresh()->sms_balance)->toBe(20);
    expect(CreditTransaction::query()->where('type', CreditTransactionType::Usage)->count())->toBe(1);
    expect(CreditTransaction::query()->where('type', CreditTransactionType::Refund)->count())->toBe(1);
});

it('holds scheduled credits so a second send cannot spend them twice', function () {
    [$user, $customer, $sender] = campaignCustomer(2);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $sender->name,
            'numbers' => ['0712445221'],
            'scheduled_at' => now()->addHour()->toIso8601String(),
        ])
        ->assertCreated()
        ->assertJsonPath('smsBalance', 1);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari tena',
            'sender_id' => $sender->name,
            'numbers' => ['0754221891', '0755000001'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('quantity');

    expect($customer->fresh()->sms_balance)->toBe(1);
    expect(CreditTransaction::query()->where('type', CreditTransactionType::Usage)->count())->toBe(1);
});

it('persists a future schedule with reserved credits and rejects a past time', function () {
    [$user, $customer, $sender] = campaignCustomer(20);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $sender->name,
            'numbers' => ['0712445221'],
            'scheduled_at' => now()->subHour()->toIso8601String(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('scheduled_at');

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $sender->name,
            'numbers' => ['0712445221'],
            'scheduled_at' => now()->addDay()->toIso8601String(),
        ])
        ->assertCreated()
        ->assertJsonPath('campaign.status', CampaignStatus::Scheduled->value)
        ->assertJsonPath('smsBalance', 19);

    $campaign = Campaign::query()->first();

    expect($customer->fresh()->sms_balance)->toBe(19)
        ->and($campaign?->queued_at)->toBeNull()
        ->and($campaign?->credit_transaction_id)->not->toBeNull()
        ->and($campaign?->reserved_segments)->toBe(1)
        ->and($campaign?->scheduled_at)->not->toBeNull();

    $this->assertDatabaseHas('campaign_recipients', [
        'campaign_id' => $campaign->id,
        'status' => CampaignRecipientStatus::Pending->value,
    ]);
});
