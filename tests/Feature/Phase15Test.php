<?php

use App\Enums\CampaignRecipientStatus;
use App\Enums\CreditTransactionType;
use App\Enums\CustomerStatus;
use App\Enums\GatewayDriver;
use App\Enums\MessageEncoding;
use App\Models\CreditTransaction;
use App\Models\Customer;
use App\Models\Provider;
use App\Models\SenderId;
use App\Models\User;
use App\Services\ApiKeyService;
use App\Services\CreditLedger;
use App\Services\Gateway\MobishastraGateway;
use App\Services\Gateway\OutboundSms;
use App\Services\SegmentationService;
use Illuminate\Support\Facades\Http;

function phase15Mobishastra(): MobishastraGateway
{
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

    return app(MobishastraGateway::class);
}

it('uses 153-character GSM concatenated segments and 63-character Unicode segments', function () {
    $segments = new SegmentationService;

    expect($segments->analyze(str_repeat('a', 306)))->toMatchArray([
        'encoding' => 'gsm',
        'segmentCount' => 2,
        'concatLimit' => 153,
    ])
        ->and($segments->analyze(str_repeat('a', 307))['segmentCount'])->toBe(3)
        ->and($segments->analyze(str_repeat('你', 126)))->toMatchArray([
            'encoding' => 'unicode',
            'segmentCount' => 2,
            'concatLimit' => 63,
        ])
        ->and($segments->analyze(str_repeat('你', 127))['segmentCount'])->toBe(3);
});

it('never mutates an older ledger row when a later movement is added', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->complete()->create(['user_id' => $user->id]);
    $ledger = app(CreditLedger::class);

    $first = $ledger->grant($customer, 100, 'First');
    $snapshot = [
        'id' => $first->id,
        'quantity' => $first->quantity,
        'description' => $first->description,
        'created_at' => $first->created_at?->toDateTimeString(),
    ];

    $ledger->grant($customer->fresh(), 50, 'Second');
    $ledger->debitUsage($customer->fresh(), 10, 'Campaign');
    $fresh = $first->fresh();

    expect([
        'id' => $fresh->id,
        'quantity' => $fresh->quantity,
        'description' => $fresh->description,
        'created_at' => $fresh->created_at?->toDateTimeString(),
    ])->toBe($snapshot)
        ->and($customer->fresh()->sms_balance)->toBe(140)
        ->and(CreditTransaction::query()->count())->toBe(3);
});

it('maps Mobishastra ShowError codes 000 through 013', function (string $body, bool $accepted, bool $timedOut, string $code) {
    Http::preventStrayRequests();
    $gateway = phase15Mobishastra();

    Http::fake([
        'https://example.invalid/sendurl.aspx*' => Http::response($body, 200),
    ]);

    $result = $gateway->send(new OutboundSms(
        1,
        '+255712445221',
        'MWALIKO',
        'Habari',
        MessageEncoding::Gsm,
    ));

    expect($result->accepted)->toBe($accepted)
        ->and($result->timedOut)->toBe($timedOut)
        ->and($result->code)->toBe($code);
})->with([
    ['000', true, false, '000'],
    ['Send Successful', true, false, '000'],
    ['001', false, false, '001'],
    ['003', false, false, '003'],
    ['005', false, false, '005'],
    ['006', false, false, '006'],
    ['007', false, false, '007'],
    ['008', false, false, '008'],
    ['009', false, false, '009'],
    ['010', false, false, '010'],
    ['011', false, false, '011'],
    ['012', false, false, '012'],
    ['013', false, true, '013'],
]);

it('blocks another customers sender id on the dashboard and the public API', function () {
    $owner = User::factory()->create();
    $ownerCustomer = Customer::factory()->complete()->create(['user_id' => $owner->id, 'sms_balance' => 20]);
    $owned = SenderId::factory()->create([
        'customer_id' => $ownerCustomer->id,
        'name' => 'OWNEDBYA',
    ]);

    $stranger = User::factory()->create();
    $strangerCustomer = Customer::factory()->complete()->create(['user_id' => $stranger->id, 'sms_balance' => 20]);
    $plain = app(ApiKeyService::class)->create($strangerCustomer, 'Phase 15')['plaintext'];

    $this->actingAs($stranger)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $owned->name,
            'numbers' => ['0712445221'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sender_id');

    $this->withHeaders(['Authorization' => 'Bearer '.$plain])
        ->postJson('/api/v1/sms/send', [
            'sender_id' => $owned->name,
            'to' => '+255712445221',
            'message' => 'Habari',
        ])
        ->assertForbidden();

    expect($strangerCustomer->fresh()->sms_balance)->toBe(20);
});

it('refunds a rejected send and does not charge a suspended account', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->complete()->create(['user_id' => $user->id, 'sms_balance' => 20]);
    $sender = SenderId::factory()->generic()->create();
    config(['sms.fake.fail_phones' => ['+255712445221']]);

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $sender->name,
            'numbers' => ['0712445221'],
        ])
        ->assertCreated();

    expect($customer->fresh()->sms_balance)->toBe(20)
        ->and(CreditTransaction::query()->where('type', CreditTransactionType::Refund)->count())->toBe(1);

    $this->assertDatabaseHas('campaign_recipients', [
        'phone' => '+255712445221',
        'status' => CampaignRecipientStatus::Failed->value,
    ]);

    $customer->update(['status' => CustomerStatus::Suspended]);
    $user->unsetRelation('customer');

    $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'source' => 'numbers',
            'message' => 'Habari',
            'sender_id' => $sender->name,
            'numbers' => ['0754221891'],
        ])
        ->assertForbidden();

    expect($customer->fresh()->sms_balance)->toBe(20);
});
