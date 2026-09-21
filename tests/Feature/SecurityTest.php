<?php

use App\Enums\AuditAction;
use App\Enums\CampaignStatus;
use App\Enums\CustomerStatus;
use App\Models\ApiIdempotencyKey;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Provider;
use App\Models\ProviderLog;
use App\Models\SenderId;
use App\Models\User;
use App\Services\ApiKeyService;
use Illuminate\Support\Facades\Http;

function securityCustomer(int $balance = 20): array
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

function securityApiKey(Customer $customer): string
{
    return app(ApiKeyService::class)->create($customer, 'Security test')['plaintext'];
}

beforeEach(function () {
    Http::preventStrayRequests();
});

it('blocks dashboard and public API send while a customer is suspended, then restores after unsuspend', function () {
    $admin = User::factory()->admin()->create();
    [$user, $customer, $sender] = securityCustomer(20);
    $plain = securityApiKey($customer);

    $this->actingAs($admin)
        ->postJson("/api/admin/customers/{$customer->id}/suspension")
        ->assertOk()
        ->assertJsonPath('customer.status', CustomerStatus::Suspended->value);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $sender->name,
            'numbers' => ['0712445221'],
        ])
        ->assertForbidden()
        ->assertJsonPath('message', 'This account is suspended. Sending is disabled.');

    $this->withHeaders(['Authorization' => 'Bearer '.$plain])
        ->postJson('/api/v1/sms/send', [
            'sender_id' => $sender->name,
            'to' => '+255712445221',
            'message' => 'Habari',
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'account_suspended');

    expect($customer->fresh()->sms_balance)->toBe(20)
        ->and(Campaign::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', AuditAction::CustomerSuspended)->count())->toBe(1);

    $this->actingAs($admin)
        ->postJson("/api/admin/customers/{$customer->id}/unsuspension")
        ->assertOk()
        ->assertJsonPath('customer.status', CustomerStatus::Active->value);

    $user->unsetRelation('customer');

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $sender->name,
            'numbers' => ['0712445221'],
        ])
        ->assertCreated();

    $this->withHeaders(['Authorization' => 'Bearer '.$plain])
        ->postJson('/api/v1/sms/send', [
            'sender_id' => $sender->name,
            'to' => '+255754221891',
            'message' => 'Habari tena',
        ])
        ->assertCreated();

    expect($customer->fresh()->sms_balance)->toBe(18);
});

it('skips scheduled dispatch while the customer is suspended and sends after unsuspend', function () {
    $admin = User::factory()->admin()->create();
    [$user, $customer, $sender] = securityCustomer(20);
    $this->freezeTime();

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $sender->name,
            'numbers' => ['0712445221'],
            'scheduled_at' => now()->addMinutes(5)->toIso8601String(),
        ])
        ->assertCreated()
        ->assertJsonPath('campaign.status', CampaignStatus::Scheduled->value);

    $this->actingAs($admin)
        ->postJson("/api/admin/customers/{$customer->id}/suspension")
        ->assertOk();

    $this->travel(5)->minutes();
    $this->artisan('sms:dispatch-scheduled')->assertSuccessful();

    $this->assertDatabaseHas('campaigns', [
        'customer_id' => $customer->id,
        'status' => CampaignStatus::Scheduled->value,
    ]);
    $this->assertDatabaseCount('deliveries', 0);
    expect($customer->fresh()->sms_balance)->toBe(20);

    $this->actingAs($admin)
        ->postJson("/api/admin/customers/{$customer->id}/unsuspension")
        ->assertOk();

    $this->artisan('sms:dispatch-scheduled')
        ->expectsOutputToContain('Released 1 scheduled campaign(s).')
        ->assertSuccessful();

    $this->assertDatabaseHas('campaigns', [
        'customer_id' => $customer->id,
        'status' => CampaignStatus::Completed->value,
    ]);
});

it('throttles dashboard send and public API v1 requests', function () {
    config([
        'sms.rate_limits.dashboard_send' => 2,
        'sms.api.rate_limit' => 2,
    ]);

    [$user, $customer, $sender] = securityCustomer(20);
    $plain = securityApiKey($customer);

    $payload = [
        'source' => 'numbers',
        'message' => 'Habari',
        'sender_id' => $sender->name,
        'numbers' => ['0712445221'],
    ];

    $this->actingAs($user)->postJson('/api/campaigns', $payload)->assertCreated();
    $this->actingAs($user)->postJson('/api/campaigns', [
        ...$payload,
        'numbers' => ['0754221891'],
    ])->assertCreated();
    $this->actingAs($user)->postJson('/api/campaigns', [
        ...$payload,
        'numbers' => ['0764221892'],
    ])->assertStatus(429);

    $this->withHeaders(['Authorization' => 'Bearer '.$plain])
        ->getJson('/api/v1/balance')
        ->assertOk();
    $this->withHeaders(['Authorization' => 'Bearer '.$plain])
        ->getJson('/api/v1/sender-ids')
        ->assertOk();
    $this->withHeaders(['Authorization' => 'Bearer '.$plain])
        ->getJson('/api/v1/balance')
        ->assertStatus(429);
});

it('rejects reserved and duplicate sender ids', function () {
    [$user] = securityCustomer();

    $this->actingAs($user)
        ->postJson('/api/sender-id-requests', [
            'name' => 'CRDB',
            'purpose' => 'Alerts',
            'organization' => 'Linkadi Ltd',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.name.0', 'This Sender ID is reserved and cannot be requested.');

    $this->actingAs($user)
        ->postJson('/api/sender-id-requests', [
            'name' => 'AB',
            'purpose' => 'Alerts',
            'organization' => 'Linkadi Ltd',
        ])
        ->assertUnprocessable();

    $this->actingAs($user)
        ->postJson('/api/sender-id-requests', [
            'name' => 'LINKADI',
            'purpose' => 'Alerts',
            'organization' => 'Linkadi Ltd',
        ])
        ->assertCreated();

    $this->actingAs($user)
        ->postJson('/api/sender-id-requests', [
            'name' => 'linkadi',
            'purpose' => 'Alerts',
            'organization' => 'Linkadi Ltd',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.name.0', 'You already have an open request for this Sender ID.');
});

it('replays public API send with the same Idempotency-Key and rejects a different body', function () {
    [, $customer, $sender] = securityCustomer(20);
    $plain = securityApiKey($customer);

    $headers = [
        'Authorization' => 'Bearer '.$plain,
        'Idempotency-Key' => 'fee-reminder-001',
    ];

    $first = $this->withHeaders($headers)
        ->postJson('/api/v1/sms/send', [
            'sender_id' => $sender->name,
            'to' => '+255712445221',
            'message' => 'Habari',
        ])
        ->assertCreated();

    $replay = $this->withHeaders($headers)
        ->postJson('/api/v1/sms/send', [
            'sender_id' => $sender->name,
            'to' => '+255712445221',
            'message' => 'Habari',
        ])
        ->assertCreated();

    expect($replay->json('id'))->toBe($first->json('id'))
        ->and($customer->fresh()->sms_balance)->toBe(19)
        ->and(Campaign::query()->count())->toBe(1)
        ->and(ApiIdempotencyKey::query()->count())->toBe(1);

    $this->withHeaders($headers)
        ->postJson('/api/v1/sms/send', [
            'sender_id' => $sender->name,
            'to' => '+255754221891',
            'message' => 'Different',
        ])
        ->assertStatus(409);

    expect($customer->fresh()->sms_balance)->toBe(19)
        ->and(Campaign::query()->count())->toBe(1);
});

it('never returns provider passwords or payloads on customer or admin json', function () {
    config([
        'sms.provider.profile_id' => '12345678',
        'sms.provider.password' => 'super-secret-pwd',
    ]);

    $admin = User::factory()->admin()->create();
    [$user, $customer] = securityCustomer();
    Provider::query()->delete();
    $provider = Provider::current();
    $provider->forceFill([
        'profile_id' => '12345678',
        'password' => 'super-secret-pwd',
    ])->save();

    ProviderLog::query()->create([
        'provider_id' => $provider->id,
        'action' => 'send_bulk',
        'request_payload' => ['pwd' => 'super-secret-pwd', 'user' => '12345678'],
        'response_payload' => ['ok' => true],
        'status' => 'ok',
    ]);

    $me = $this->actingAs($user)->getJson('/api/me')->assertOk()->json();
    $campaigns = $this->actingAs($user)->getJson('/api/campaigns')->assertOk()->json();
    $plain = securityApiKey($customer);
    $balance = $this->withHeaders(['Authorization' => 'Bearer '.$plain])
        ->getJson('/api/v1/balance')
        ->assertOk()
        ->json();

    foreach ([$me, $campaigns, $balance] as $payload) {
        $encoded = json_encode($payload);
        expect($encoded)->not->toContain('super-secret-pwd')
            ->and($encoded)->not->toContain('"pwd"');
    }

    $logs = $this->actingAs($admin)->getJson('/api/admin/logs')->assertOk();
    $encodedLogs = $logs->content();
    expect($encodedLogs)->not->toContain('super-secret-pwd')
        ->and($logs->json('providerLogs.0'))->not->toHaveKey('request_payload');

    $this->actingAs($admin)
        ->getJson('/api/admin/provider-balance')
        ->assertOk()
        ->assertJsonMissingPath('password')
        ->assertJsonPath('profileId', '****5678');
});
