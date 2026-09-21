<?php

use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEvent;
use App\Models\Customer;
use App\Models\SenderId;
use App\Models\User;
use App\Models\WebhookDelivery;
use Database\Seeders\PricingTierSeeder;
use Illuminate\Support\Facades\Http;

function webhookCustomer(int $balance = 20): array
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
    $this->seed(PricingTierSeeder::class);
});

it('creates a webhook, shows the secret once, and posts a signed delivery.updated event', function () {
    [$user, $customer, $sender] = webhookCustomer();
    Http::fake([
        'https://hooks.example.test/*' => Http::response(['ok' => true], 200),
    ]);

    $create = $this->actingAs($user)
        ->postJson('/api/webhooks', [
            'name' => 'School CRM',
            'url' => 'https://hooks.example.test/soscom',
            'events' => [WebhookEvent::DeliveryUpdated->value, WebhookEvent::CampaignCompleted->value],
        ])
        ->assertCreated();

    $secret = $create->json('secret');
    expect($secret)->toStartWith('whsec_');

    $this->actingAs($user)
        ->getJson('/api/webhooks')
        ->assertOk()
        ->assertJsonMissing(['secret' => $secret]);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $sender->name,
            'numbers' => ['0712445221'],
            'name' => 'Webhook DLR',
        ])
        ->assertCreated();

    Http::assertSent(function ($request) use ($secret): bool {
        if ($request->url() !== 'https://hooks.example.test/soscom') {
            return false;
        }

        $header = $request->header('X-Soscom-Signature');
        $signature = is_array($header) ? (string) ($header[0] ?? '') : (string) $header;
        if (! str_starts_with($signature, 't=')) {
            return false;
        }

        [$tPart, $vPart] = explode(',', $signature, 2);
        $timestamp = substr($tPart, 2);
        $hex = substr($vPart, 3);
        $body = $request->body();
        $expected = hash_hmac('sha256', $timestamp.'.'.$body, $secret);
        $payload = json_decode($body, true);

        return hash_equals($expected, $hex)
            && is_array($payload)
            && ($payload['type'] ?? null) === WebhookEvent::DeliveryUpdated->value;
    });

    expect(WebhookDelivery::query()->where('status', WebhookDeliveryStatus::Delivered)->count())
        ->toBeGreaterThan(0)
        ->and($customer->fresh()->sms_balance)->toBe(19);
});

it('retries a failed webhook and records the attempt', function () {
    [$user, $customer, $sender] = webhookCustomer();
    Http::fake([
        'https://hooks.example.test/*' => Http::response('nope', 500),
    ]);

    $this->actingAs($user)
        ->postJson('/api/webhooks', [
            'name' => 'Broken CRM',
            'url' => 'https://hooks.example.test/down',
            'events' => [WebhookEvent::DeliveryUpdated->value],
        ])
        ->assertCreated();

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $sender->name,
            'numbers' => ['0712445221'],
        ])
        ->assertCreated();

    $delivery = WebhookDelivery::query()->latest('id')->first();
    expect($delivery)->not->toBeNull()
        ->and($delivery->status)->toBe(WebhookDeliveryStatus::Pending)
        ->and($delivery->attempt)->toBe(1)
        ->and($delivery->next_retry_at)->not->toBeNull();

    $delivery->forceFill(['next_retry_at' => now()->subMinute()])->save();

    $this->artisan('webhooks:retry')->assertSuccessful();

    expect($delivery->fresh()->attempt)->toBe(2);
});

it('rejects a plain http webhook that is not local', function () {
    [$user] = webhookCustomer();

    $this->actingAs($user)
        ->postJson('/api/webhooks', [
            'name' => 'Insecure',
            'url' => 'http://example.com/hook',
            'events' => [WebhookEvent::DeliveryUpdated->value],
        ])
        ->assertUnprocessable();
});
