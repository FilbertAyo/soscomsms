<?php

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
use App\Services\Gateway\MobishastraGateway;
use App\Services\Gateway\OutboundSms;
use App\Services\Gateway\UnicodeHex;
use Illuminate\Support\Facades\Http;

function gatewayCustomer(int $balance = 20): array
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

it('does not deduct credits again when the fake gateway accepts a campaign', function () {
    [$user, $customer, $sender] = gatewayCustomer(20);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $sender->name,
            'numbers' => ['0712445221'],
        ])
        ->assertCreated()
        ->assertJsonPath('campaign.status', CampaignStatus::Completed->value)
        ->assertJsonPath('smsBalance', 19)
        ->assertJsonPath('campaign.delivered', 1);

    expect($customer->fresh()->sms_balance)->toBe(19)
        ->and(CreditTransaction::query()->where('type', CreditTransactionType::Usage)->count())->toBe(1)
        ->and(Delivery::query()->whereNotNull('provider_message_id')->count())->toBe(1);

    $campaign = Campaign::query()->first();
    app(CampaignDispatcher::class)->dispatch($campaign);

    expect($customer->fresh()->sms_balance)->toBe(19)
        ->and(Delivery::query()->count())->toBe(1)
        ->and(CreditTransaction::query()->where('type', CreditTransactionType::Usage)->count())->toBe(1);
});

it('refunds only rejected recipients and does not retry a timed-out send', function () {
    [$user, $customer, $sender] = gatewayCustomer(20);
    config([
        'sms.fake.fail_phones' => ['+255712445221'],
        'sms.fake.timeout_phones' => ['+255754221891'],
    ]);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $sender->name,
            'numbers' => ['+255712445221', '+255754221891'],
        ])
        ->assertCreated()
        ->assertJsonPath('campaign.status', CampaignStatus::Failed->value)
        ->assertJsonPath('smsBalance', 19);

    expect($customer->fresh()->sms_balance)->toBe(19)
        ->and(CreditTransaction::query()->where('type', CreditTransactionType::Usage)->count())->toBe(1)
        ->and(CreditTransaction::query()->where('type', CreditTransactionType::Refund)->count())->toBe(1);

    $this->assertDatabaseHas('campaign_recipients', [
        'phone' => '+255712445221',
        'status' => CampaignRecipientStatus::Failed->value,
    ]);
    $this->assertDatabaseHas('campaign_recipients', [
        'phone' => '+255754221891',
        'status' => CampaignRecipientStatus::Unknown->value,
    ]);

    $campaign = Campaign::query()->first();
    Http::fake();
    app(CampaignDispatcher::class)->dispatch($campaign->fresh());
    Http::assertNothingSent();

    expect($customer->fresh()->sms_balance)->toBe(19)
        ->and(Delivery::query()->whereNotNull('provider_message_id')->count())->toBe(0);
});

it('lets an admin probe a Mobishastra DLR without saving it', function () {
    $admin = User::factory()->admin()->create();
    config([
        'sms.gateway' => GatewayDriver::Mobishastra->value,
        'sms.provider.base_url' => 'https://example.invalid',
        'sms.provider.profile_id' => 'SOSCOM',
        'sms.provider.password' => 'secret',
        'sms.provider.dlr_path' => 'dlrstatus_api.aspx',
    ]);
    Provider::query()->delete();
    Provider::factory()->mobishastra()->create([
        'base_url' => 'https://example.invalid',
        'profile_id' => 'SOSCOM',
        'password' => 'secret',
    ]);

    Http::fake([
        'https://example.invalid/dlrstatus_api.aspx*' => Http::response(
            '1871080898,DELIVRD,2026-09-23 11:20:00',
            200
        ),
    ]);

    $this->actingAs($admin)
        ->postJson('/api/admin/provider-balance/dlr', [
            'message_id' => '1871080898',
        ])
        ->assertOk()
        ->assertJsonPath('rawResponse', '1871080898,DELIVRD,2026-09-23 11:20:00')
        ->assertJsonPath('dlrStatus', 'DELIVRD')
        ->assertJsonPath('soscomStatus', CampaignRecipientStatus::Delivered->value)
        ->assertJsonMissingPath('password');

    expect(Delivery::query()->count())->toBe(0);
});

it('lets an admin read the fake provider balance', function () {
    $admin = User::factory()->admin()->create();
    Provider::current();

    $this->actingAs($admin)
        ->getJson('/api/admin/provider-balance')
        ->assertOk()
        ->assertJsonPath('driver', GatewayDriver::Fake->value)
        ->assertJsonPath('smsBalance', 100000)
        ->assertJsonPath('customerCredits', 0)
        ->assertJsonPath('realSmsBalance', 100000)
        ->assertJsonPath('parsed', true)
        ->assertJsonMissingPath('password');
});

it('subtracts unused customer credits from the real remaining provider balance', function () {
    $admin = User::factory()->admin()->create();
    Provider::current();
    Customer::factory()->create(['sms_balance' => 20]);
    Customer::factory()->create(['sms_balance' => 80]);

    $this->actingAs($admin)
        ->getJson('/api/admin/provider-balance')
        ->assertOk()
        ->assertJsonPath('smsBalance', 100000)
        ->assertJsonPath('customerCredits', 100)
        ->assertJsonPath('realSmsBalance', 99900);
});

it('reads the Tanzania row from a Mobishastra country balance reply', function () {
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
    ]);

    Http::fake([
        'https://example.invalid/balance.aspx*' => Http::response('Tanzania(+255) = 5009', 200),
    ]);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->getJson('/api/admin/provider-balance')
        ->assertOk()
        ->assertJsonPath('driver', GatewayDriver::Mobishastra->value)
        ->assertJsonPath('smsBalance', 5009)
        ->assertJsonPath('parsed', true)
        ->assertJsonPath('rawResponse', 'Tanzania(+255) = 5009');
});

it('does not invent a balance from a Mobishastra error body', function () {
    config([
        'sms.gateway' => GatewayDriver::Mobishastra->value,
        'sms.provider.base_url' => 'https://example.invalid',
        'sms.provider.profile_id' => 'SOSCOM',
        'sms.provider.password' => 'secret',
    ]);
    Provider::query()->delete();
    $provider = Provider::factory()->mobishastra()->create([
        'base_url' => 'https://example.invalid',
        'profile_id' => 'SOSCOM',
        'password' => 'secret',
        'last_balance' => 12000,
    ]);

    Http::fake([
        'https://example.invalid/balance.aspx*' => Http::response('Invalid Password', 200),
    ]);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->getJson('/api/admin/provider-balance')
        ->assertOk()
        ->assertJsonPath('parsed', false)
        ->assertJsonPath('smsBalance', 12000)
        ->assertJsonPath('rawResponse', 'Invalid Password');

    expect($provider->fresh()?->last_balance)->toBe(12000);
});

it('keeps the Mobishastra message id from a single send reply', function () {
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
        'https://example.invalid/sendurl.aspx*' => Http::response('000,M-77', 200),
    ]);

    $gateway = new MobishastraGateway;
    $result = $gateway->send(new OutboundSms(
        9,
        '+255755237692',
        'MWALIKO',
        'Habari',
        MessageEncoding::Gsm,
    ));

    expect($result->accepted)->toBeTrue()
        ->and($result->providerMessageId)->toBe('M-77');

    $this->assertDatabaseHas('provider_logs', [
        'action' => 'send',
        'status' => 'ok',
    ]);
});

it('posts Mobishastra JSON for bulk send and hex-encodes Unicode', function () {
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
    ]);

    Http::fake([
        'https://example.invalid/sendsms_api_json.aspx' => Http::response([
            [
                'msg_id' => 'M-99',
                'number' => '255712445221',
                'response' => '000',
            ],
            [
                'msg_id' => 'M-100',
                'number' => '255754221891',
                'response' => '000',
            ],
        ], 200),
    ]);

    $gateway = new MobishastraGateway;
    $results = $gateway->sendBulk([
        new OutboundSms(1, '+255712445221', 'MWALIKO', 'Habari', MessageEncoding::Gsm),
        new OutboundSms(2, '+255754221891', 'MWALIKO', 'Habari—ada', MessageEncoding::Unicode, 1),
    ]);

    expect($results[0]->accepted)->toBeTrue()
        ->and($results[0]->providerMessageId)->toBe('M-99')
        ->and($results[1]->accepted)->toBeTrue()
        ->and(UnicodeHex::encode('Habari—ada'))->toBe(strtoupper(bin2hex(mb_convert_encoding('Habari—ada', 'UTF-16BE', 'UTF-8'))));

    Http::assertSent(function ($request): bool {
        if (! str_contains($request->url(), 'sendsms_api_json.aspx')) {
            return false;
        }

        $payload = $request->data();

        return $payload[0]['language'] === 'English'
            && $payload[1]['language'] === 'Unicode'
            && $payload[1]['msg'] === UnicodeHex::encode('Habari—ada')
            && $payload[0]['user'] === 'SOSCOM'
            && $payload[0]['pwd'] === 'secret';
    });
});

it('polls dlrstatus_api with messageid and applies Message_id,DLR_Status,DLR_Time', function () {
    config([
        'sms.gateway' => GatewayDriver::Mobishastra->value,
        'sms.provider.base_url' => 'https://example.invalid',
        'sms.provider.profile_id' => 'SOSCOM',
        'sms.provider.password' => 'secret',
        'sms.provider.dlr_path' => 'dlrstatus_api.aspx',
    ]);
    Provider::query()->delete();
    $provider = Provider::factory()->mobishastra()->create([
        'base_url' => 'https://example.invalid',
        'profile_id' => 'SOSCOM',
        'password' => 'secret',
    ]);

    $recipient = CampaignRecipient::factory()->create([
        'phone' => '+255712445221',
        'status' => CampaignRecipientStatus::Sent,
    ]);
    Delivery::factory()->create([
        'campaign_recipient_id' => $recipient->id,
        'provider_id' => $provider->id,
        'provider_message_id' => 'M-99',
        'status' => DeliveryStatus::Submitted,
        'sent_at' => now(),
    ]);

    Http::fake([
        'https://example.invalid/dlrstatus_api.aspx*' => Http::response('M-99,Delivered,2026-09-20 13:00:00', 200),
    ]);

    $this->artisan('sms:poll-delivery-reports')
        ->expectsOutputToContain('Updated 1')
        ->assertSuccessful();

    Http::assertSent(function ($request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return str_contains($request->url(), 'dlrstatus_api.aspx')
            && ($query['user'] ?? $request['user'] ?? null) === 'SOSCOM'
            && ($query['messageid'] ?? $request['messageid'] ?? null) === 'M-99';
    });

    $this->assertDatabaseHas('campaign_recipients', [
        'id' => $recipient->id,
        'status' => CampaignRecipientStatus::Delivered->value,
    ]);
});

it('leaves existing status unchanged when the DLR poll body is empty', function () {
    config([
        'sms.gateway' => GatewayDriver::Mobishastra->value,
        'sms.provider.base_url' => 'https://example.invalid',
        'sms.provider.profile_id' => 'SOSCOM',
        'sms.provider.password' => 'secret',
        'sms.provider.dlr_path' => 'dlrstatus_api.aspx',
    ]);
    Provider::query()->delete();
    $provider = Provider::factory()->mobishastra()->create([
        'base_url' => 'https://example.invalid',
        'profile_id' => 'SOSCOM',
        'password' => 'secret',
    ]);

    $recipient = CampaignRecipient::factory()->create([
        'phone' => '+255712445221',
        'status' => CampaignRecipientStatus::Sent,
    ]);
    Delivery::factory()->create([
        'campaign_recipient_id' => $recipient->id,
        'provider_id' => $provider->id,
        'provider_message_id' => 'M-empty',
        'status' => DeliveryStatus::Submitted,
        'sent_at' => now(),
    ]);

    Http::fake([
        'https://example.invalid/dlrstatus_api.aspx*' => Http::response('', 200),
    ]);

    $this->artisan('sms:poll-delivery-reports')
        ->expectsOutputToContain('Updated 0')
        ->assertSuccessful();

    $this->assertDatabaseHas('campaign_recipients', [
        'id' => $recipient->id,
        'status' => CampaignRecipientStatus::Sent->value,
    ]);
});

it('polls DELIVRD and upgrades an unknown recipient to delivered', function () {
    config([
        'sms.gateway' => GatewayDriver::Mobishastra->value,
        'sms.provider.base_url' => 'https://example.invalid',
        'sms.provider.profile_id' => 'SOSCOM',
        'sms.provider.password' => 'secret',
        'sms.provider.dlr_path' => 'dlrstatus_api.aspx',
    ]);
    Provider::query()->delete();
    $provider = Provider::factory()->mobishastra()->create([
        'base_url' => 'https://example.invalid',
        'profile_id' => 'SOSCOM',
        'password' => 'secret',
    ]);

    $recipient = CampaignRecipient::factory()->create([
        'phone' => '+255712445221',
        'status' => CampaignRecipientStatus::Unknown,
    ]);
    Delivery::factory()->create([
        'campaign_recipient_id' => $recipient->id,
        'provider_id' => $provider->id,
        'provider_message_id' => '1871080898',
        'status' => DeliveryStatus::Unknown,
        'sent_at' => now(),
    ]);

    Http::fake([
        'https://example.invalid/dlrstatus_api.aspx*' => Http::response(
            '1871080898,DELIVRD,September 23, 2026, 11:20 AM',
            200
        ),
    ]);

    $this->artisan('sms:poll-delivery-reports')
        ->expectsOutputToContain('Updated 1')
        ->assertSuccessful();

    $this->assertDatabaseHas('campaign_recipients', [
        'id' => $recipient->id,
        'status' => CampaignRecipientStatus::Delivered->value,
    ]);
    $this->assertDatabaseHas('deliveries', [
        'provider_message_id' => '1871080898',
        'status' => DeliveryStatus::Delivered->value,
    ]);
});

it('skips DLR polling while the fake gateway is active', function () {
    $this->artisan('sms:poll-delivery-reports')
        ->expectsOutputToContain('Skipped')
        ->assertSuccessful();
});

it('does not let a customer see provider credentials', function () {
    [$user] = gatewayCustomer();

    $this->actingAs($user)
        ->getJson('/api/admin/provider-balance')
        ->assertForbidden();
});
