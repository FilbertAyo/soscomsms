<?php

use App\Models\ApiKey;
use App\Models\ApiLog;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\SenderId;
use App\Models\User;
use App\Services\ApiKeyService;
use Illuminate\Support\Facades\Http;

function publicApiCustomer(int $balance = 20): array
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

function publicApiKey(Customer $customer, string $name = 'Website'): string
{
    return app(ApiKeyService::class)->create($customer, $name)['plaintext'];
}

beforeEach(function () {
    Http::preventStrayRequests();
});

it('creates an API key, shows plaintext once, then only a masked value', function () {
    [$user] = publicApiCustomer();

    $create = $this->actingAs($user)
        ->postJson('/api/api-keys', ['name' => 'School website'])
        ->assertCreated();

    $plain = $create->json('key');
    expect($plain)->toStartWith('sk_live_')
        ->and($create->json('apiKey.masked'))->toStartWith('sk_live_')
        ->and($create->json('apiKey.masked'))->toContain('••••')
        ->and($create->json('apiKey.masked'))->not->toBe($plain);

    $this->assertDatabaseHas('api_keys', [
        'key_hash' => ApiKeyService::hash($plain),
        'name' => 'School website',
    ]);
    $this->assertDatabaseMissing('api_keys', [
        'key_hash' => $plain,
    ]);

    $this->actingAs($user)
        ->getJson('/api/api-keys')
        ->assertOk()
        ->assertJsonMissing(['key' => $plain])
        ->assertJsonPath('keys.0.masked', $create->json('apiKey.masked'))
        ->assertJsonPath('keys.0.status', 'active');
});

it('sends SMS with a public API key, deducts credits, and shows the campaign on the dashboard', function () {
    [$user, $customer, $sender] = publicApiCustomer(20);
    $plain = publicApiKey($customer);

    $this->withHeaders(['Authorization' => 'Bearer '.$plain])
        ->postJson('/api/v1/sms/send', [
            'sender_id' => $sender->name,
            'to' => '+255712445221',
            'message' => 'Habari kutoka SOSCOM',
            'name' => 'API fee reminder',
        ])
        ->assertCreated()
        ->assertJsonPath('sender_id', $sender->name)
        ->assertJsonPath('recipients', 1)
        ->assertJsonPath('sms', 1)
        ->assertJsonPath('sms_balance', 19)
        ->assertJsonPath('messages.0.to', '+255712445221');

    expect($customer->fresh()->sms_balance)->toBe(19);

    $campaign = Campaign::query()->where('customer_id', $customer->id)->first();
    expect($campaign)->not->toBeNull()
        ->and($campaign->name)->toBe('API fee reminder');

    $this->actingAs($user)
        ->getJson('/api/campaigns')
        ->assertOk()
        ->assertJsonPath('campaigns.0.id', $campaign->id)
        ->assertJsonPath('campaigns.0.senderId', $sender->name);

    $this->withHeaders(['Authorization' => 'Bearer '.$plain])
        ->getJson('/api/v1/balance')
        ->assertOk()
        ->assertJsonPath('sms_balance', 19);

    $this->withHeaders(['Authorization' => 'Bearer '.$plain])
        ->getJson('/api/v1/sms/'.$campaign->id)
        ->assertOk()
        ->assertJsonPath('id', $campaign->id);

    $this->withHeaders(['Authorization' => 'Bearer '.$plain])
        ->getJson('/api/v1/sender-ids')
        ->assertOk()
        ->assertJsonFragment(['name' => $sender->name]);

    expect(ApiLog::query()->where('campaign_id', $campaign->id)->exists())->toBeTrue();
});

it('rejects another customer sender id with 403', function () {
    [, $owner] = publicApiCustomer();
    $foreign = SenderId::factory()->create([
        'customer_id' => $owner->id,
        'name' => 'OWNEDBYB',
    ]);

    [, $stranger] = publicApiCustomer(20);
    $plain = publicApiKey($stranger);

    $this->withHeaders(['Authorization' => 'Bearer '.$plain])
        ->postJson('/api/v1/sms/send', [
            'sender_id' => $foreign->name,
            'to' => '+255712445221',
            'message' => 'Habari',
        ])
        ->assertForbidden()
        ->assertJsonPath('errors.sender_id.0', 'This Sender ID is not available on this account.');

    expect($stranger->fresh()->sms_balance)->toBe(20);
});

it('rejects a revoked API key', function () {
    [$user, $customer, $sender] = publicApiCustomer();

    $create = $this->actingAs($user)
        ->postJson('/api/api-keys', ['name' => 'Temp key'])
        ->assertCreated();
    $plain = $create->json('key');
    $id = $create->json('apiKey.id');

    $this->actingAs($user)
        ->postJson("/api/api-keys/{$id}/revoke")
        ->assertOk()
        ->assertJsonPath('apiKey.status', 'revoked');

    $this->withHeaders(['Authorization' => 'Bearer '.$plain])
        ->postJson('/api/v1/sms/send', [
            'sender_id' => $sender->name,
            'to' => '+255712445221',
            'message' => 'Habari',
        ])
        ->assertUnauthorized()
        ->assertJsonPath('code', 'invalid_api_key');
});

it('hides another customer campaign from a public API key', function () {
    [, $owner, $sender] = publicApiCustomer();
    $ownerKey = publicApiKey($owner);

    $send = $this->withHeaders(['Authorization' => 'Bearer '.$ownerKey])
        ->postJson('/api/v1/sms/send', [
            'sender_id' => $sender->name,
            'to' => '+255712445221',
            'message' => 'Private',
        ])
        ->assertCreated();

    [, $stranger] = publicApiCustomer();
    $strangerKey = publicApiKey($stranger);

    $this->withHeaders(['Authorization' => 'Bearer '.$strangerKey])
        ->getJson('/api/v1/sms/'.$send->json('id'))
        ->assertNotFound();
});

it('returns 402 when the public API send does not have enough credits', function () {
    [, $customer, $sender] = publicApiCustomer(0);
    $plain = publicApiKey($customer);

    $this->withHeaders(['Authorization' => 'Bearer '.$plain])
        ->postJson('/api/v1/sms/send', [
            'sender_id' => $sender->name,
            'to' => '+255712445221',
            'message' => 'Habari',
        ])
        ->assertStatus(402);
});

it('lets an admin see real API usage logs', function () {
    $admin = User::factory()->admin()->create();
    [, $customer] = publicApiCustomer();
    $plain = publicApiKey($customer);

    $this->withHeaders(['Authorization' => 'Bearer '.$plain])
        ->getJson('/api/v1/balance')
        ->assertOk();

    $this->actingAs($admin)
        ->getJson('/api/admin/api-usage/keys')
        ->assertOk()
        ->assertJsonPath('keys.0.masked', ApiKey::query()->first()->masked())
        ->assertJsonPath('meta.total', 1)
        ->assertJsonMissing(['key' => $plain]);

    $this->actingAs($admin)
        ->getJson('/api/admin/api-usage/requests')
        ->assertOk()
        ->assertJsonPath('logs.0.path', '/api/v1/balance')
        ->assertJsonPath('logs.0.statusCode', 200)
        ->assertJsonPath('meta.currentPage', 1)
        ->assertJsonMissing(['key' => $plain]);

    $this->actingAs($admin)
        ->getJson('/api/admin/api-usage/webhook-deliveries')
        ->assertOk()
        ->assertJsonPath('meta.total', 0);
});

it('paginates and searches admin API usage', function () {
    $admin = User::factory()->admin()->create();
    [, $customer] = publicApiCustomer();
    [, $other] = publicApiCustomer();
    $key = app(ApiKeyService::class)->create($customer, 'School portal')['key'];
    app(ApiKeyService::class)->create($other, 'Clinic app');

    foreach (range(1, 25) as $index) {
        ApiLog::query()->create([
            'api_key_id' => $key->id,
            'customer_id' => $customer->id,
            'method' => 'GET',
            'path' => '/api/v1/balance',
            'status_code' => 200,
            'request_id' => 'req-'.$index,
            'created_at' => now(),
        ]);
    }
    ApiLog::query()->create([
        'customer_id' => $other->id,
        'method' => 'POST',
        'path' => '/api/v1/sms/send',
        'status_code' => 402,
        'request_id' => 'req-credit',
        'error' => 'Not enough SMS credits.',
        'created_at' => now(),
    ]);

    $this->actingAs($admin)
        ->getJson('/api/admin/api-usage/requests?page=2')
        ->assertOk()
        ->assertJsonPath('meta.total', 26)
        ->assertJsonPath('meta.lastPage', 2)
        ->assertJsonCount(6, 'logs');

    $this->actingAs($admin)
        ->getJson('/api/admin/api-usage/requests?q=402')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('logs.0.requestId', 'req-credit');

    $this->actingAs($admin)
        ->getJson('/api/admin/api-usage/requests?q='.urlencode($customer->user->email))
        ->assertOk()
        ->assertJsonPath('meta.total', 25);

    $this->actingAs($admin)
        ->getJson('/api/admin/api-usage/keys?q=clinic')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('keys.0.name', 'Clinic app');
});

it('keeps admin API usage away from customers', function () {
    [$user] = publicApiCustomer();

    $this->actingAs($user)->getJson('/api/admin/api-usage/requests')->assertForbidden();
    $this->actingAs($user)->getJson('/api/admin/api-usage/keys')->assertForbidden();
    $this->actingAs($user)->getJson('/api/admin/api-usage/webhook-deliveries')->assertForbidden();
});
