<?php

use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\CreditTransactionType;
use App\Jobs\DispatchCampaignJob;
use App\Models\Campaign;
use App\Models\CreditTransaction;
use App\Models\Customer;
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
