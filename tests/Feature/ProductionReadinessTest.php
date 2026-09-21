<?php

use App\Enums\AuditAction;
use App\Enums\GatewayDriver;
use App\Enums\MessageEncoding;
use App\Exceptions\ProviderNotReadyException;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Provider;
use App\Models\SenderId;
use App\Models\User;
use App\Notifications\ProviderLowBalanceNotification;
use App\Services\Gateway\MobishastraGateway;
use App\Services\Gateway\OutboundSms;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('lets an admin record the static IP and confirm Mobishastra whitelist plus Tanzania account', function () {
    $admin = User::factory()->admin()->create();
    config([
        'sms.provider.profile_id' => '12345678',
        'sms.provider.password' => 'secret',
        'sms.provider.base_url' => 'https://tz.example.invalid',
    ]);

    $this->actingAs($admin)
        ->getJson('/api/admin/production')
        ->assertOk()
        ->assertJsonPath('driver', GatewayDriver::Fake->value)
        ->assertJsonPath('localUsesFake', true)
        ->assertJsonPath('canSendViaMobishastra', false)
        ->assertJsonMissingPath('provider.password');

    $this->actingAs($admin)
        ->patchJson('/api/admin/production', [
            'static_ip' => '203.0.113.10',
            'base_url' => 'https://tz.example.invalid',
            'country_code' => 'ALL',
            'low_balance_threshold' => 8000,
        ])
        ->assertOk()
        ->assertJsonPath('provider.staticIp', '203.0.113.10')
        ->assertJsonPath('provider.countryCode', 'ALL');

    $whitelist = $this->actingAs($admin)
        ->postJson('/api/admin/production/whitelist')
        ->assertOk();

    expect($whitelist->json('provider.staticIpWhitelistedAt'))->not->toBeEmpty();

    $account = $this->actingAs($admin)
        ->postJson('/api/admin/production/account')
        ->assertOk();

    expect($account->json('provider.credentialsConfirmedAt'))->not->toBeEmpty();

    expect(AuditLog::query()->where('action', AuditAction::ProviderWhitelistConfirmed)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::ProviderAccountConfirmed)->count())->toBe(1)
        ->and(Provider::query()->where('driver', GatewayDriver::Mobishastra)->value('static_ip'))->toBe('203.0.113.10');
});

it('blocks Mobishastra sends until the static IP is whitelisted and the Tanzania account is confirmed', function () {
    config(['sms.gateway' => GatewayDriver::Mobishastra->value]);
    Provider::query()->delete();
    Provider::factory()->mobishastra()->create([
        'static_ip' => null,
        'static_ip_whitelisted_at' => null,
        'credentials_confirmed_at' => null,
    ]);

    $gateway = app(MobishastraGateway::class);

    expect(fn () => $gateway->sendBulk([
        new OutboundSms(1, '+255712445221', 'MWALIKO', 'Habari', MessageEncoding::Gsm),
    ]))->toThrow(ProviderNotReadyException::class);

    Http::assertNothingSent();
});

it('keeps the fake gateway working without a Mobishastra whitelist', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->complete()->create([
        'user_id' => $user->id,
        'sms_balance' => 20,
    ]);
    $sender = SenderId::factory()->generic()->create([
        'name' => 'HUDUMA',
    ]);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $sender->name,
            'numbers' => ['0712445221'],
        ])
        ->assertCreated();

    expect($customer->fresh()->sms_balance)->toBe(19);
});

it('emails admins when the provider balance is below the threshold', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    config(['sms.fake.balance' => 5]);
    Provider::current()->forceFill([
        'low_balance_threshold' => 10000,
        'fake_balance' => 5,
        'low_balance_alerted_at' => null,
    ])->save();

    $this->artisan('sms:check-provider-balance')
        ->expectsOutputToContain('Admins were notified')
        ->assertSuccessful();

    Notification::assertSentTo($admin, ProviderLowBalanceNotification::class);
    expect(Provider::current()->fresh()->low_balance_alerted_at)->not->toBeNull();
});

it('shows the Mobishastra static IP on provider balance while local stays on fake', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->patchJson('/api/admin/production', [
            'static_ip' => '203.0.113.10',
        ])
        ->assertOk();

    $this->actingAs($admin)
        ->getJson('/api/admin/provider-balance')
        ->assertOk()
        ->assertJsonPath('driver', GatewayDriver::Fake->value)
        ->assertJsonPath('staticIp', '203.0.113.10')
        ->assertJsonMissingPath('password');
});

it('skips in-memory sqlite backups and forbids customers from production settings', function () {
    $this->artisan('sms:backup-database')
        ->expectsOutputToContain('in-memory')
        ->assertSuccessful();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/admin/production')
        ->assertForbidden();
});
