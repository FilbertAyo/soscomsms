<?php

use App\Contracts\SmsGatewayInterface;
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
use App\Services\Gateway\DeliveryReport;
use App\Services\Gateway\GatewaySendResult;
use App\Services\Gateway\OutboundSms;
use App\Services\Gateway\ProviderBalance;
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

function bindCapturingGateway(): object
{
    $bucket = new stdClass;
    $bucket->sent = [];

    $fake = new class($bucket) implements SmsGatewayInterface
    {
        public function __construct(private stdClass $bucket) {}

        public function send(OutboundSms $message): GatewaySendResult
        {
            return $this->sendBulk([$message])[0];
        }

        public function sendBulk(array $messages): array
        {
            $results = [];
            foreach ($messages as $message) {
                $this->bucket->sent[] = $message;
                $results[] = new GatewaySendResult(
                    recipientId: $message->recipientId,
                    phone: $message->phone,
                    accepted: true,
                    providerMessageId: 'FAKE-R'.$message->recipientId,
                    code: '000',
                    raw: 'ok',
                );
            }

            return $results;
        }

        public function checkBalance(): ProviderBalance
        {
            return new ProviderBalance(100000, 'fake');
        }

        public function checkDeliveryStatus(string $providerMessageId): DeliveryReport
        {
            return new DeliveryReport($providerMessageId, 'delivered', 'fake');
        }
    };

    app()->instance(SmsGatewayInterface::class, $fake);

    return $bucket;
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

it('sends each person their own name and previews the first one', function () {
    [$user, $customer, $sender] = campaignCustomer(20);
    $bucket = bindCapturingGateway();

    $this->actingAs($user)
        ->postJson('/api/sms/preview', [
            'source' => 'numbers',
            'personalize' => true,
            'message' => 'Hello {name}, karibu.',
            'sender_id' => $sender->name,
            'named_recipients' => [
                ['phone' => '0712445221', 'name' => 'Amina'],
                ['phone' => '0754221891', 'name' => 'Ámina'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('personalized', true)
        ->assertJsonPath('placeholderReady', true)
        ->assertJsonPath('missingNameCount', 0)
        ->assertJsonPath('recipientCount', 2)
        ->assertJsonPath('example.name', 'Amina')
        ->assertJsonPath('example.phone', '+255712445221')
        ->assertJsonPath('example.message', 'Hello Amina, karibu.')
        ->assertJsonPath('totalSms', 2);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'personalize' => true,
            'message' => 'Hello {name}, karibu.',
            'sender_id' => $sender->name,
            'named_recipients' => [
                ['phone' => '0712445221', 'name' => 'Amina'],
                ['phone' => '0754221891', 'name' => 'Ámina'],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('campaign.personalized', true)
        ->assertJsonPath('campaign.recipients', 2)
        ->assertJsonPath('smsBalance', 18);

    $campaign = Campaign::query()->first();
    expect($customer->fresh()->sms_balance)->toBe(18)
        ->and($bucket->sent)->toHaveCount(2)
        ->and($bucket->sent[0]->body)->toBe('Hello Amina, karibu.')
        ->and($bucket->sent[0]->encoding->value)->toBe('gsm')
        ->and($bucket->sent[1]->body)->toBe('Hello Ámina, karibu.')
        ->and($bucket->sent[1]->encoding->value)->toBe('unicode');

    $this->assertDatabaseHas('campaign_recipients', [
        'campaign_id' => $campaign->id,
        'phone' => '+255712445221',
        'recipient_name' => 'Amina',
        'body' => 'Hello Amina, karibu.',
    ]);

    $this->actingAs($user)
        ->getJson('/api/delivery-reports')
        ->assertOk()
        ->assertJsonFragment(['message' => 'Hello Amina, karibu.'])
        ->assertJsonFragment(['message' => 'Hello Ámina, karibu.']);
});

it('charges the longer personalized message as more sms', function () {
    [$user, $customer, $sender] = campaignCustomer(20);
    $body = '{name}'.str_repeat('a', 156);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'personalize' => true,
            'message' => $body,
            'sender_id' => $sender->name,
            'named_recipients' => [
                ['phone' => '0712445221', 'name' => 'Bo'],
                ['phone' => '0754221891', 'name' => 'Bartholomew'],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('campaign.totalSms', 3)
        ->assertJsonPath('smsBalance', 17);

    expect($customer->fresh()->sms_balance)->toBe(17);
    $this->assertDatabaseHas('campaign_recipients', [
        'phone' => '+255712445221',
        'segment_count' => 1,
    ]);
    $this->assertDatabaseHas('campaign_recipients', [
        'phone' => '+255754221891',
        'segment_count' => 2,
    ]);
});

it('keeps a shared message unchanged when names are off', function () {
    [$user, , $sender] = campaignCustomer(20);
    $bucket = bindCapturingGateway();

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Hello {name}',
            'sender_id' => $sender->name,
            'numbers' => ['0712445221', '0754221891'],
        ])
        ->assertCreated()
        ->assertJsonPath('campaign.personalized', false)
        ->assertJsonPath('campaign.totalSms', 2)
        ->assertJsonPath('smsBalance', 18);

    expect($bucket->sent)->toHaveCount(2)
        ->and($bucket->sent[0]->body)->toBe('Hello {name}')
        ->and($bucket->sent[1]->body)->toBe('Hello {name}');

    $this->assertDatabaseHas('campaign_recipients', [
        'phone' => '+255712445221',
        'recipient_name' => null,
        'body' => null,
    ]);
});

it('refuses a personalized send when a number has no name', function () {
    [$user, $customer, $sender] = campaignCustomer(20);

    $this->actingAs($user)
        ->postJson('/api/sms/preview', [
            'source' => 'numbers',
            'personalize' => true,
            'message' => 'Hello {name}',
            'named_recipients' => [
                ['phone' => '0712445221', 'name' => 'Amina'],
                ['phone' => '0754221891', 'name' => ''],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('recipientCount', 1)
        ->assertJsonPath('missingNameCount', 1);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'personalize' => true,
            'message' => 'Hello {name}',
            'sender_id' => $sender->name,
            'named_recipients' => [
                ['phone' => '0712445221', 'name' => 'Amina'],
                ['phone' => '0754221891', 'name' => ''],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('recipients');

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'personalize' => true,
            'message' => 'Hello there',
            'sender_id' => $sender->name,
            'named_recipients' => [
                ['phone' => '0712445221', 'name' => 'Amina'],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message');

    expect($customer->fresh()->sms_balance)->toBe(20);
    $this->assertDatabaseCount('campaigns', 0);
});

it('fills {name} from the contact saved on a group', function () {
    [$user, $customer, $sender] = campaignCustomer(20);
    $bucket = bindCapturingGateway();
    $group = ContactGroup::factory()->create([
        'customer_id' => $customer->id,
        'name' => 'Parents',
    ]);
    Contact::factory()->create([
        'customer_id' => $customer->id,
        'contact_group_id' => $group->id,
        'name' => 'Amina',
        'phone' => '+255712445221',
    ]);
    Contact::factory()->create([
        'customer_id' => $customer->id,
        'contact_group_id' => $group->id,
        'name' => 'Juma',
        'phone' => '+255754221891',
    ]);
    Contact::factory()->inactive()->create([
        'customer_id' => $customer->id,
        'contact_group_id' => $group->id,
        'name' => 'Hidden',
        'phone' => '+255755000001',
    ]);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'group',
            'personalize' => true,
            'group_id' => $group->id,
            'message' => 'Habari {name}',
            'sender_id' => $sender->name,
        ])
        ->assertCreated()
        ->assertJsonPath('campaign.recipients', 2)
        ->assertJsonPath('smsBalance', 18);

    expect($bucket->sent)->toHaveCount(2)
        ->and($bucket->sent[0]->body)->toBe('Habari Amina')
        ->and($bucket->sent[1]->body)->toBe('Habari Juma');

    $this->assertDatabaseMissing('campaign_recipients', [
        'phone' => '+255755000001',
    ]);
});
