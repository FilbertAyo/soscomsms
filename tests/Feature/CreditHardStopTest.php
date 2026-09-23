<?php

use App\Contracts\SmsGatewayInterface;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\CreditTransactionType;
use App\Enums\DeliveryStatus;
use App\Enums\GatewayDriver;
use App\Enums\MessageEncoding;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\CreditTransaction;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Provider;
use App\Models\SenderId;
use App\Models\User;
use App\Services\CampaignDispatcher;
use App\Services\Gateway\GatewaySendResult;
use App\Services\Gateway\MobishastraGateway;
use App\Services\Gateway\OutboundSms;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('never calls the gateway after the campaign reservation is exhausted', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->complete()->create([
        'user_id' => $user->id,
        'sms_balance' => 800,
    ]);
    $sender = SenderId::factory()->generic()->create([
        'name' => strtoupper(fake()->unique()->lexify('??????')),
    ]);

    $gatewayCalls = 0;
    $fake = new class($gatewayCalls) implements SmsGatewayInterface
    {
        public function __construct(private int &$calls) {}

        public function send(OutboundSms $message): GatewaySendResult
        {
            return $this->sendBulk([$message])[0];
        }

        public function sendBulk(array $messages): array
        {
            $this->calls += count($messages);
            $results = [];
            foreach ($messages as $message) {
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

        public function checkBalance(): \App\Services\Gateway\ProviderBalance
        {
            return new \App\Services\Gateway\ProviderBalance(100000, 'fake');
        }

        public function checkDeliveryStatus(string $providerMessageId): \App\Services\Gateway\DeliveryReport
        {
            return new \App\Services\Gateway\DeliveryReport($providerMessageId, 'delivered', 'fake');
        }
    };

    app()->instance(SmsGatewayInterface::class, $fake);
    config(['sms.fake.auto_dlr' => false]);

    $numbers = [];
    for ($i = 0; $i < 400; $i++) {
        $numbers[] = '0712'.str_pad((string) $i, 6, '0', STR_PAD_LEFT);
    }

    // 400 recipients × 2 segments = 800 SMS units (exact reservation).
    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => str_repeat('a', 161),
            'sender_id' => $sender->name,
            'numbers' => $numbers,
            'name' => 'Incident regression',
        ])
        ->assertCreated()
        ->assertJsonPath('campaign.totalSms', 800)
        ->assertJsonPath('smsBalance', 0);

    $campaign = Campaign::query()->firstOrFail();
    expect($campaign->reserved_segments)->toBe(800)
        ->and($campaign->consumed_segments)->toBe(800)
        ->and($customer->fresh()->sms_balance)->toBe(0)
        ->and($gatewayCalls)->toBe(400);

    // Force an extra recipient that somehow remains queued — must not call gateway.
    $extra = CampaignRecipient::factory()->create([
        'campaign_id' => $campaign->id,
        'message_id' => $campaign->message_id,
        'phone' => '+255799999999',
        'status' => CampaignRecipientStatus::Queued,
        'segment_count' => 2,
    ]);
    $campaign->forceFill([
        'status' => CampaignStatus::Sending,
        'recipient_count' => 401,
    ])->save();

    $callsBefore = $gatewayCalls;
    app(CampaignDispatcher::class)->dispatchChunk($campaign->fresh(), [$extra->id]);

    expect($gatewayCalls)->toBe($callsBefore)
        ->and($extra->fresh()->status)->toBe(CampaignRecipientStatus::Failed)
        ->and($extra->fresh()->delivery?->provider_response_code)->toBe('no_credits');
});

it('does not resend an already-submitted recipient after a simulated worker restart', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->complete()->create([
        'user_id' => $user->id,
        'sms_balance' => 5,
    ]);
    $sender = SenderId::factory()->generic()->create([
        'name' => strtoupper(fake()->unique()->lexify('??????')),
    ]);

    $gatewayCalls = 0;
    $fake = new class($gatewayCalls) implements SmsGatewayInterface
    {
        public function __construct(private int &$calls) {}

        public function send(OutboundSms $message): GatewaySendResult
        {
            return $this->sendBulk([$message])[0];
        }

        public function sendBulk(array $messages): array
        {
            $this->calls += count($messages);
            $results = [];
            foreach ($messages as $message) {
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

        public function checkBalance(): \App\Services\Gateway\ProviderBalance
        {
            return new \App\Services\Gateway\ProviderBalance(100000, 'fake');
        }

        public function checkDeliveryStatus(string $providerMessageId): \App\Services\Gateway\DeliveryReport
        {
            return new \App\Services\Gateway\DeliveryReport($providerMessageId, 'delivered', 'fake');
        }
    };

    app()->instance(SmsGatewayInterface::class, $fake);
    config(['sms.fake.auto_dlr' => false]);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $sender->name,
            'numbers' => ['0712445221', '0754221891'],
            'name' => 'Restart safe',
            'scheduled_at' => now()->addHour()->toIso8601String(),
        ])
        ->assertCreated();

    $campaign = Campaign::query()->firstOrFail();
    $campaign->forceFill([
        'status' => CampaignStatus::Sending,
        'queued_at' => now(),
    ])->save();

    $first = $campaign->recipients()->orderBy('id')->firstOrFail();
    $first->update(['status' => CampaignRecipientStatus::Sent]);
    Delivery::factory()->create([
        'campaign_recipient_id' => $first->id,
        'provider_id' => Provider::current()->id,
        'status' => DeliveryStatus::Submitted,
        'provider_message_id' => 'FAKE-R'.$first->id,
        'attempted_at' => now()->subMinute(),
        'sent_at' => now()->subMinute(),
    ]);
    $campaign->forceFill(['consumed_segments' => 1])->save();

    $second = $campaign->recipients()->orderBy('id')->skip(1)->firstOrFail();
    $second->update(['status' => CampaignRecipientStatus::Queued]);

    $callsBefore = $gatewayCalls;
    app(CampaignDispatcher::class)->dispatch($campaign->fresh(['message', 'customer']));

    expect($gatewayCalls)->toBe($callsBefore + 1)
        ->and($first->fresh()->delivery?->provider_message_id)->toBe('FAKE-R'.$first->id)
        ->and($second->fresh()->status)->toBe(CampaignRecipientStatus::Sent)
        ->and($customer->fresh()->sms_balance)->toBe(3);
});

it('treats Mobishastra success without a real message id as uncertain', function () {
    config([
        'sms.gateway' => GatewayDriver::Mobishastra->value,
        'sms.provider.base_url' => 'https://example.invalid',
        'sms.provider.profile_id' => 'SOSCOM',
        'sms.provider.password' => 'secret',
    ]);
    Provider::query()->delete();
    Provider::factory()->mobishastra()->create([
        'base_url' => 'https://example.invalid',
        'profile_id' => 'SOSCOM',
        'password' => 'secret',
        'static_ip' => '1.1.1.1',
        'static_ip_whitelisted_at' => now(),
        'credentials_confirmed_at' => now(),
    ]);

    Http::fake([
        'https://example.invalid/sendurl.aspx*' => Http::response('000', 200),
    ]);

    $result = (new MobishastraGateway)->send(new OutboundSms(
        9,
        '+255755237692',
        'MWALIKO',
        'Habari',
        MessageEncoding::Gsm,
    ));

    expect($result->accepted)->toBeFalse()
        ->and($result->timedOut)->toBeTrue()
        ->and($result->providerMessageId)->toBeNull();
});

it('exposes reserved and consumed SMS on the campaign payload', function () {
    $user = User::factory()->create();
    Customer::factory()->complete()->create([
        'user_id' => $user->id,
        'sms_balance' => 20,
    ]);
    $sender = SenderId::factory()->generic()->create([
        'name' => strtoupper(fake()->unique()->lexify('??????')),
    ]);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $sender->name,
            'numbers' => ['0712445221'],
            'scheduled_at' => now()->addHour()->toIso8601String(),
        ])
        ->assertCreated()
        ->assertJsonPath('campaign.reservedSms', 1)
        ->assertJsonPath('campaign.consumedSms', 0)
        ->assertJsonPath('campaign.remainingReservedSms', 1);

    expect(CreditTransaction::query()->where('type', CreditTransactionType::Usage)->count())->toBe(1);
});
