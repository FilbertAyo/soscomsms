<?php

use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\CreditTransactionType;
use App\Enums\DeliveryStatus;
use App\Models\Campaign;
use App\Models\CreditTransaction;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\SenderId;
use App\Models\User;
use App\Services\Gateway\ProviderDeliveryStatus;
use Illuminate\Support\Facades\Http;

function reportCustomer(int $balance = 20): array
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

it('normalizes provider delivery statuses to Soscom enums', function () {
    expect(ProviderDeliveryStatus::normalize('DELIVRD'))->toBe(CampaignRecipientStatus::Delivered)
        ->and(ProviderDeliveryStatus::normalize('Undelivered'))->toBe(CampaignRecipientStatus::Failed)
        ->and(ProviderDeliveryStatus::normalize('DND'))->toBe(CampaignRecipientStatus::Rejected)
        ->and(ProviderDeliveryStatus::normalize('Submitted'))->toBe(CampaignRecipientStatus::Sent)
        ->and(ProviderDeliveryStatus::normalize('Unknown'))->toBe(CampaignRecipientStatus::Unknown)
        ->and(ProviderDeliveryStatus::normalize('not-a-status'))->toBeNull()
        ->and(ProviderDeliveryStatus::normalize(''))->toBeNull();
});

it('marks fake gateway sends as delivered and lists them on reports', function () {
    [$user, $customer, $sender] = reportCustomer(20);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $sender->name,
            'numbers' => ['0712445221'],
            'name' => 'DLR demo',
        ])
        ->assertCreated()
        ->assertJsonPath('campaign.status', CampaignStatus::Completed->value)
        ->assertJsonPath('campaign.delivered', 1);

    $campaignId = Campaign::query()->value('id');
    $this->actingAs($user)
        ->getJson('/api/campaigns/'.$campaignId)
        ->assertOk()
        ->assertJsonPath('campaign.recipientRows.0.status', CampaignRecipientStatus::Delivered->value);

    expect($customer->fresh()->sms_balance)->toBe(19);

    $this->actingAs($user)
        ->getJson('/api/delivery-reports')
        ->assertOk()
        ->assertJsonPath('reports.0.phone', '+255712445221')
        ->assertJsonPath('reports.0.status', CampaignRecipientStatus::Delivered->value)
        ->assertJsonPath('reports.0.campaignName', 'DLR demo');

    $csv = $this->actingAs($user)
        ->get('/api/delivery-reports/export')
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain('+255712445221')
        ->and($csv)->toContain('delivered')
        ->and($csv)->toContain('DLR demo');
});

it('applies an inbound delivery report without refunding a later failure', function () {
    [$user, $customer, $sender] = reportCustomer(20);
    config(['sms.fake.auto_dlr' => false]);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $sender->name,
            'numbers' => ['0712445221'],
        ])
        ->assertCreated()
        ->assertJsonPath('campaign.status', CampaignStatus::Completed->value)
        ->assertJsonPath('campaign.recipientPhone', '+255712445221');

    $this->actingAs($user)
        ->getJson('/api/campaigns/'.Campaign::query()->value('id'))
        ->assertOk()
        ->assertJsonPath('campaign.recipientRows.0.status', CampaignRecipientStatus::Sent->value);

    $delivery = Delivery::query()->first();
    expect($delivery?->provider_message_id)->not->toBeNull();

    $this->postJson('/api/provider/delivery-reports', [
        'msgid' => $delivery->provider_message_id,
        'status' => 'Undelivered',
        'mobileno' => '255712445221',
    ])
        ->assertOk()
        ->assertJsonPath('matched', true);

    expect($customer->fresh()->sms_balance)->toBe(19)
        ->and(CreditTransaction::query()->where('type', CreditTransactionType::Refund)->count())->toBe(0);

    $this->assertDatabaseHas('campaign_recipients', [
        'phone' => '+255712445221',
        'status' => CampaignRecipientStatus::Failed->value,
    ]);
    $this->assertDatabaseHas('campaigns', [
        'id' => $delivery->campaignRecipient?->campaign_id,
        'status' => CampaignStatus::Failed->value,
    ]);

    $this->postJson('/api/provider/delivery-reports', [
        'messageid' => $delivery->provider_message_id,
        'DLR_Status' => 'Delivered',
        'DLR_Time' => '2026-09-20 13:00:00',
        'mobileno' => '255712445221',
    ])
        ->assertOk()
        ->assertJsonPath('matched', true);

    $this->assertDatabaseHas('campaign_recipients', [
        'phone' => '+255712445221',
        'status' => CampaignRecipientStatus::Failed->value,
    ]);
});

it('lets a later delivery report resolve an unknown send', function () {
    [$user, , $sender] = reportCustomer(20);
    config(['sms.fake.auto_dlr' => false]);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $sender->name,
            'numbers' => ['0712445221'],
        ])
        ->assertCreated();

    $delivery = Delivery::query()->first();
    $recipient = $delivery?->campaignRecipient;
    expect($recipient)->not->toBeNull();

    $recipient->status = CampaignRecipientStatus::Unknown;
    $recipient->save();
    $delivery->status = DeliveryStatus::Unknown;
    $delivery->save();

    $this->getJson('/api/provider/delivery-reports?'.http_build_query([
        'msgid' => $delivery->provider_message_id,
        'stat' => 'DELIVRD',
        'mobileno' => '255712445221',
    ]))
        ->assertOk()
        ->assertJsonPath('matched', true);

    $this->assertDatabaseHas('campaign_recipients', [
        'id' => $recipient->id,
        'status' => CampaignRecipientStatus::Delivered->value,
    ]);
    $this->assertDatabaseHas('campaigns', [
        'id' => $recipient->campaign_id,
        'status' => CampaignStatus::Completed->value,
        'delivered_count' => 1,
    ]);
});

it('applies provider Unknown as not confirmed without changing credits', function () {
    [$user, $customer, $sender] = reportCustomer(20);
    config(['sms.fake.auto_dlr' => false]);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $sender->name,
            'numbers' => ['0712445221'],
        ])
        ->assertCreated();

    $delivery = Delivery::query()->first();
    expect($delivery?->provider_message_id)->not->toBeNull();

    $this->postJson('/api/provider/delivery-reports', [
        'msgid' => $delivery->provider_message_id,
        'status' => 'Unknown',
        'mobileno' => '255712445221',
    ])
        ->assertOk()
        ->assertJsonPath('matched', true);

    $this->assertDatabaseHas('campaign_recipients', [
        'phone' => '+255712445221',
        'status' => CampaignRecipientStatus::Unknown->value,
    ]);
    expect($customer->fresh()->sms_balance)->toBe(19);

    $this->actingAs($user)
        ->getJson('/api/delivery-reports?status=unknown')
        ->assertOk()
        ->assertJsonPath('reports.0.status', CampaignRecipientStatus::Unknown->value)
        ->assertJsonPath('reports.0.phone', '+255712445221');
});

it('hides another customers delivery reports and requires a DLR token when configured', function () {
    [$owner, , $sender] = reportCustomer(20);
    [$stranger] = reportCustomer(20);

    $this->actingAs($owner)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $sender->name,
            'numbers' => ['0712445221'],
        ])
        ->assertCreated();

    $this->actingAs($stranger)
        ->getJson('/api/delivery-reports')
        ->assertOk()
        ->assertJsonCount(0, 'reports');

    $this->actingAs($owner)
        ->getJson('/api/delivery-reports')
        ->assertOk()
        ->assertJsonCount(1, 'reports');

    $this->actingAs($owner)
        ->getJson('/api/delivery-reports?q=0712445221')
        ->assertOk()
        ->assertJsonCount(1, 'reports')
        ->assertJsonPath('reports.0.phone', '+255712445221');

    config(['sms.provider.dlr_token' => 'secret-dlr']);

    $this->postJson('/api/provider/delivery-reports', [
        'msgid' => 'FAKE-missing',
        'status' => 'Delivered',
    ])->assertUnauthorized();

    $this->postJson('/api/provider/delivery-reports', [
        'msgid' => 'FAKE-missing',
        'status' => 'Delivered',
    ], ['X-Soscom-Provider-Token' => 'secret-dlr'])
        ->assertOk()
        ->assertJsonPath('matched', false);
});

it('does not refund when fake auto DLR reports undelivered', function () {
    [$user, $customer, $sender] = reportCustomer(20);
    config(['sms.fake.fail_dlr_phones' => ['+255712445221']]);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $sender->name,
            'numbers' => ['0712445221'],
        ])
        ->assertCreated()
        ->assertJsonPath('campaign.status', CampaignStatus::Failed->value);

    $this->actingAs($user)
        ->getJson('/api/campaigns/'.Campaign::query()->value('id'))
        ->assertOk()
        ->assertJsonPath('campaign.recipientRows.0.status', CampaignRecipientStatus::Failed->value);

    expect($customer->fresh()->sms_balance)->toBe(19)
        ->and(CreditTransaction::query()->where('type', CreditTransactionType::Refund)->count())->toBe(0);
});
