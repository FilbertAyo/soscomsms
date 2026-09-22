<?php

use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\CreditTransactionType;
use App\Enums\DeliveryStatus;
use App\Jobs\DispatchCampaignJob;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\CreditTransaction;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Provider;
use App\Models\SenderId;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function scheduleCustomer(int $balance = 20): array
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

it('uses East Africa Time for Tanzania', function () {
    expect(config('app.timezone'))->toBe('Africa/Dar_es_Salaam')
        ->and(now()->timezoneName)->toBe('Africa/Dar_es_Salaam');

    $scheduled = CarbonImmutable::parse('2026-09-20T14:00', config('app.timezone'));

    expect($scheduled->utcOffset())->toBe(180)
        ->and($scheduled->toIso8601String())->toContain('+03:00');
});

it('sends immediately on confirm so a dead queue cannot hide the SMS', function () {
    [$user, $customer, $sender] = scheduleCustomer(80);
    Queue::fake();

    $numbers = [];
    for ($i = 0; $i < 50; $i++) {
        $numbers[] = '0712'.str_pad((string) $i, 6, '0', STR_PAD_LEFT);
    }

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $sender->name,
            'numbers' => $numbers,
            'name' => 'Queue chunk demo',
        ])
        ->assertCreated()
        ->assertJsonPath('campaign.status', CampaignStatus::Completed->value)
        ->assertJsonPath('campaign.recipients', 50)
        ->assertJsonPath('smsBalance', 30);

    expect($customer->fresh()->sms_balance)->toBe(30);
    $this->assertDatabaseCount('deliveries', 50);
    Queue::assertNotPushed(DispatchCampaignJob::class);
});

it('releases a due scheduled campaign through SOSCOM and never calls a provider schedule date', function () {
    [$user, $customer, $sender] = scheduleCustomer(20);
    $this->freezeTime();

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $sender->name,
            'numbers' => ['0712445221'],
            'name' => 'Fee reminder later',
            'scheduled_at' => now()->addMinutes(5)->toIso8601String(),
        ])
        ->assertCreated()
        ->assertJsonPath('campaign.status', CampaignStatus::Scheduled->value)
        ->assertJsonPath('smsBalance', 20);

    $this->artisan('sms:dispatch-scheduled')
        ->assertSuccessful();

    $this->assertDatabaseHas('campaigns', [
        'name' => 'Fee reminder later',
        'status' => CampaignStatus::Scheduled->value,
    ]);
    $this->assertDatabaseCount('deliveries', 0);

    $this->travel(5)->minutes();

    $this->artisan('sms:dispatch-scheduled')
        ->expectsOutputToContain('Released 1 scheduled campaign(s).')
        ->assertSuccessful();

    $this->assertDatabaseHas('campaigns', [
        'name' => 'Fee reminder later',
        'status' => CampaignStatus::Completed->value,
        'delivered_count' => 1,
    ]);
    $this->assertDatabaseHas('campaign_recipients', [
        'phone' => '+255712445221',
        'status' => CampaignRecipientStatus::Delivered->value,
    ]);
    expect($customer->fresh()->sms_balance)->toBe(19);
});

it('cancels a scheduled campaign without a refund and does not send later', function () {
    [$user, $customer, $sender] = scheduleCustomer(20);
    [$stranger] = scheduleCustomer(20);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $sender->name,
            'numbers' => ['0712445221'],
            'scheduled_at' => now()->addHour()->toIso8601String(),
        ])
        ->assertCreated();

    $campaign = Campaign::query()->first();

    $this->actingAs($stranger)
        ->postJson('/api/campaigns/'.$campaign->id.'/cancel')
        ->assertNotFound();

    $this->actingAs($user)
        ->postJson('/api/campaigns/'.$campaign->id.'/cancel')
        ->assertOk()
        ->assertJsonPath('campaign.status', CampaignStatus::Cancelled->value)
        ->assertJsonPath('smsBalance', 20);

    expect($customer->fresh()->sms_balance)->toBe(20)
        ->and(CreditTransaction::query()->count())->toBe(0);

    $this->actingAs($user)
        ->postJson('/api/campaigns/'.$campaign->id.'/cancel')
        ->assertUnprocessable();

    $campaign->update(['scheduled_at' => now()->subMinute()]);

    $this->artisan('sms:dispatch-scheduled')->assertSuccessful();

    $this->assertDatabaseHas('campaigns', [
        'id' => $campaign->id,
        'status' => CampaignStatus::Cancelled->value,
    ]);
    $this->assertDatabaseCount('deliveries', 0);
});

it('does not cancel a campaign that already started sending', function () {
    [$user, , $sender] = scheduleCustomer(20);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $sender->name,
            'numbers' => ['0712445221'],
        ])
        ->assertCreated()
        ->assertJsonPath('campaign.status', CampaignStatus::Completed->value);

    $campaign = Campaign::query()->first();

    $this->actingAs($user)
        ->postJson('/api/campaigns/'.$campaign->id.'/cancel')
        ->assertUnprocessable();
});

it('marks mixed delivery results as partially delivered', function () {
    [$user, , $sender] = scheduleCustomer(20);
    config([
        'sms.fake.fail_dlr_phones' => ['+255712445221'],
    ]);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $sender->name,
            'numbers' => ['0712445221', '0754221891'],
        ])
        ->assertCreated()
        ->assertJsonPath('campaign.status', CampaignStatus::PartiallyDelivered->value)
        ->assertJsonPath('campaign.delivered', 1)
        ->assertJsonPath('campaign.failed', 1);
});

it('cancels stuck attempted recipients instead of resending them', function () {
    [$user, $customer, $sender] = scheduleCustomer(50);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $sender->name,
            'numbers' => ['0712445221', '0754221891', '0765000001'],
            'name' => 'Stuck bulk',
            'scheduled_at' => now()->addMinutes(5)->toIso8601String(),
        ])
        ->assertCreated()
        ->assertJsonPath('campaign.status', CampaignStatus::Scheduled->value);

    $campaign = Campaign::query()->firstOrFail();
    $message = $campaign->message;

    $campaign->forceFill([
        'status' => CampaignStatus::Sending,
        'queued_at' => now()->subMinutes(10),
        'recipient_count' => 3,
        'total_segments' => 3,
    ])->save();

    $campaign->recipients()->delete();

    $sent = CampaignRecipient::factory()->create([
        'campaign_id' => $campaign->id,
        'message_id' => $message->id,
        'phone' => '+255712445221',
        'status' => CampaignRecipientStatus::Sent,
        'segment_count' => 1,
    ]);
    Delivery::factory()->create([
        'campaign_recipient_id' => $sent->id,
        'provider_id' => Provider::current()->id,
        'status' => DeliveryStatus::Submitted,
        'provider_message_id' => 'MSH-R'.$sent->id,
        'attempted_at' => now()->subMinutes(10),
        'sent_at' => now()->subMinutes(10),
    ]);

    $stuckPhones = ['+255754221891', '+255765000001'];
    foreach ($stuckPhones as $phone) {
        $recipient = CampaignRecipient::factory()->create([
            'campaign_id' => $campaign->id,
            'message_id' => $message->id,
            'phone' => $phone,
            'status' => CampaignRecipientStatus::Queued,
            'segment_count' => 1,
        ]);
        Delivery::factory()->create([
            'campaign_recipient_id' => $recipient->id,
            'provider_id' => Provider::current()->id,
            'status' => DeliveryStatus::Sending,
            'provider_message_id' => null,
            'attempted_at' => now()->subMinutes(10),
        ]);
    }

    $this->artisan('sms:dispatch-scheduled')
        ->expectsOutputToContain('Cancelled 1 stuck sending campaign(s).')
        ->assertSuccessful();

    $this->assertDatabaseHas('campaigns', [
        'id' => $campaign->id,
        'status' => CampaignStatus::PartiallyDelivered->value,
    ]);
    $this->assertDatabaseHas('campaign_recipients', [
        'phone' => '+255754221891',
        'status' => CampaignRecipientStatus::Failed->value,
    ]);
    $this->assertDatabaseHas('campaign_recipients', [
        'phone' => '+255765000001',
        'status' => CampaignRecipientStatus::Failed->value,
    ]);
    $this->assertDatabaseHas('deliveries', [
        'provider_response_code' => 'cancelled',
    ]);
    // Only the already-sent SMS is billed.
    expect($customer->fresh()->sms_balance)->toBe(49);
});

it('does not resend a recently attempted delivery without a provider message id', function () {
    [$user, , $sender] = scheduleCustomer(20);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $sender->name,
            'numbers' => ['0712445221'],
            'scheduled_at' => now()->addHour()->toIso8601String(),
        ])
        ->assertCreated();

    $campaign = Campaign::query()->firstOrFail();
    $campaign->forceFill([
        'status' => CampaignStatus::Sending,
        'queued_at' => now(),
    ])->save();

    $recipient = $campaign->recipients()->firstOrFail();
    $recipient->update(['status' => CampaignRecipientStatus::Queued]);
    Delivery::factory()->create([
        'campaign_recipient_id' => $recipient->id,
        'provider_id' => Provider::current()->id,
        'status' => DeliveryStatus::Sending,
        'attempted_at' => now()->subSeconds(30),
    ]);

    $this->artisan('sms:dispatch-scheduled')
        ->expectsOutputToContain('Cancelled 1 stuck sending campaign(s).')
        ->assertSuccessful();

    $this->assertDatabaseHas('campaign_recipients', [
        'id' => $recipient->id,
        'status' => CampaignRecipientStatus::Failed->value,
    ]);
    $this->assertDatabaseHas('deliveries', [
        'campaign_recipient_id' => $recipient->id,
        'provider_response_code' => 'cancelled',
    ]);
});
