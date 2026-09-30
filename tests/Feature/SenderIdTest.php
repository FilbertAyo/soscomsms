<?php

use App\Enums\SenderIdRequestStatus;
use App\Enums\SenderIdSource;
use App\Enums\SenderIdStatus;
use App\Models\Customer;
use App\Models\SenderId;
use App\Models\SenderIdRequest;
use App\Models\User;
use App\Services\SenderIdService;
use App\Support\SenderIdName;
use Illuminate\Validation\ValidationException;

function senderCustomer(): array
{
    $user = User::factory()->create();
    $customer = Customer::factory()->complete()->create(['user_id' => $user->id]);

    return [$user, $customer];
}

it('keeps sender id letter case and rejects spaces', function () {
    expect(SenderIdName::normalize('  AdiTZ '))->toBe('AdiTZ')
        ->and(SenderIdName::isValid('ADITZ'))->toBeTrue()
        ->and(SenderIdName::isValid('ADI TZ'))->toBeFalse()
        ->and(SenderIdName::isValid('SEND OFF'))->toBeFalse()
        ->and(SenderIdName::isValid('SEND OFF', allowSpaces: true))->toBeTrue()
        ->and(SenderIdName::isValid('SEND-OFF', allowSpaces: true))->toBeFalse()
        ->and(SenderIdName::isValid('LI'))->toBeFalse()
        ->and(SenderIdName::isValid('TOO-LONG-NAME'))->toBeFalse();
});

it('lets a customer request a sender id and hides it from another customer', function () {
    [$owner] = senderCustomer();
    [$stranger] = senderCustomer();

    $this->actingAs($owner)
        ->postJson('/api/sender-id-requests', [
            'name' => 'linkadi',
            'purpose' => 'Customer updates',
            'organization' => 'Linkadi Ltd',
        ])
        ->assertCreated()
        ->assertJsonPath('request.name', 'linkadi')
        ->assertJsonPath('request.status', SenderIdRequestStatus::Submitted->value);

    $id = $owner->customer->senderIdRequests()->value('id');

    $this->actingAs($stranger)
        ->getJson('/api/sender-id-requests')
        ->assertOk()
        ->assertJsonCount(0, 'requests');

    $this->actingAs($stranger)
        ->getJson("/api/sender-id-requests/{$id}")
        ->assertNotFound();
});

it('rejects a sender id request that contains a space', function () {
    [$owner] = senderCustomer();

    $this->actingAs($owner)
        ->postJson('/api/sender-id-requests', [
            'name' => 'ADI TZ',
            'purpose' => 'Customer updates',
            'organization' => 'Adi Ltd',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

it('lets every customer use a generic sender id that already contains a space', function () {
    [$firstUser] = senderCustomer();
    [$secondUser] = senderCustomer();

    SenderId::factory()->generic()->create(['name' => 'SEND OFF']);

    $this->actingAs($firstUser)
        ->getJson('/api/sender-ids')
        ->assertOk()
        ->assertJsonPath('senderIds.0.name', 'SEND OFF');

    $this->actingAs($secondUser)
        ->postJson('/api/sender-ids/assert', ['sender_id' => 'send off'])
        ->assertOk()
        ->assertJsonPath('senderId.name', 'SEND OFF');
});

it('lets both customers use a generic sender id but not each others custom id', function () {
    [$firstUser, $firstCustomer] = senderCustomer();
    [$secondUser, $secondCustomer] = senderCustomer();

    SenderId::factory()->generic()->create(['name' => 'MWALIKO']);
    SenderId::factory()->create([
        'customer_id' => $firstCustomer->id,
        'name' => 'LINKADI',
        'source' => SenderIdSource::Request,
    ]);

    $this->actingAs($firstUser)
        ->getJson('/api/sender-ids')
        ->assertOk()
        ->assertJsonPath('senderIds.0.name', 'LINKADI')
        ->assertJsonPath('senderIds.1.name', 'MWALIKO');

    $this->actingAs($secondUser)
        ->getJson('/api/sender-ids')
        ->assertOk()
        ->assertJsonCount(1, 'senderIds')
        ->assertJsonPath('senderIds.0.name', 'MWALIKO');

    $this->actingAs($secondUser)
        ->postJson('/api/sender-ids/assert', ['sender_id' => 'LINKADI'])
        ->assertUnprocessable();

    $this->actingAs($secondUser)
        ->postJson('/api/sender-ids/assert', ['sender_id' => 'mwaliko'])
        ->assertOk()
        ->assertJsonPath('senderId.name', 'MWALIKO');

    expect(app(SenderIdService::class)->assertUsable($secondCustomer, 'MWALIKO')->name)->toBe('MWALIKO');

    expect(fn () => app(SenderIdService::class)->assertUsable($secondCustomer, 'LINKADI'))
        ->toThrow(ValidationException::class);
});

it('rejects a request for a name that is already taken', function () {
    [$user] = senderCustomer();
    SenderId::factory()->generic()->create(['name' => 'HUDUMA']);

    $this->actingAs($user)
        ->postJson('/api/sender-id-requests', [
            'name' => 'HUDUMA',
            'purpose' => 'Alerts',
            'organization' => 'Linkadi Ltd',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

it('does not let an admin approve before submitting to the provider', function () {
    $admin = User::factory()->admin()->create();
    [$user] = senderCustomer();

    $this->actingAs($user)
        ->postJson('/api/sender-id-requests', [
            'name' => 'SOSCOM',
            'purpose' => 'Alerts',
            'organization' => 'SOSCOM',
        ])
        ->assertCreated();

    $id = $user->customer->senderIdRequests()->value('id');

    $this->actingAs($admin)
        ->postJson("/api/admin/sender-id-requests/{$id}/approval")
        ->assertUnprocessable();
});

it('records a manual provider submission then approval as an owned active sender id', function () {
    $admin = User::factory()->admin()->create();
    [$user, $customer] = senderCustomer();
    $request = SenderIdRequest::factory()->create([
        'customer_id' => $customer->id,
        'name' => 'SOSCOM',
        'status' => SenderIdRequestStatus::UnderReview,
    ]);

    $this->actingAs($admin)
        ->postJson("/api/admin/sender-id-requests/{$request->id}/provider-submissions", [
            'submitted_to_provider_at' => '2026-09-20',
            'provider_reference' => 'email-41',
            'notes' => 'Sent to account manager',
        ])
        ->assertOk()
        ->assertJsonPath('request.status', SenderIdRequestStatus::SubmittedToProvider->value)
        ->assertJsonPath('request.providerReference', 'email-41');

    $this->actingAs($admin)
        ->postJson("/api/admin/sender-id-requests/{$request->id}/approval")
        ->assertOk()
        ->assertJsonPath('request.status', SenderIdRequestStatus::Approved->value);

    $this->actingAs($user)
        ->getJson('/api/sender-ids')
        ->assertOk()
        ->assertJsonPath('senderIds.0.name', 'SOSCOM')
        ->assertJsonPath('senderIds.0.source', SenderIdSource::Request->value);

    $this->assertDatabaseHas('sender_ids', [
        'name' => 'SOSCOM',
        'customer_id' => $customer->id,
        'status' => SenderIdStatus::Active->value,
    ]);
});

it('requires a rejection reason and does not create a sender id', function () {
    $admin = User::factory()->admin()->create();
    [$user, $customer] = senderCustomer();
    $request = SenderIdRequest::factory()->submittedToProvider()->create([
        'customer_id' => $customer->id,
        'name' => 'REJECTME',
    ]);

    $this->actingAs($admin)
        ->postJson("/api/admin/sender-id-requests/{$request->id}/rejection", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('rejection_reason');

    $this->actingAs($admin)
        ->postJson("/api/admin/sender-id-requests/{$request->id}/rejection", [
            'rejection_reason' => 'Mobishastra declined this name.',
        ])
        ->assertOk()
        ->assertJsonPath('request.status', SenderIdRequestStatus::Rejected->value);

    $this->actingAs($user)
        ->getJson('/api/sender-ids')
        ->assertOk()
        ->assertJsonCount(0, 'senderIds');

    expect(SenderId::query()->count())->toBe(0);
});

it('forbids customers from recording provider outcomes', function () {
    [$user, $customer] = senderCustomer();
    $request = SenderIdRequest::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($user)
        ->postJson("/api/admin/sender-id-requests/{$request->id}/provider-submissions")
        ->assertForbidden();
});

it('hides an assigned-only catalog name until an admin grants it', function () {
    $admin = User::factory()->admin()->create();
    [$user, $customer] = senderCustomer();
    [$otherUser] = senderCustomer();

    $create = $this->actingAs($admin)
        ->postJson('/api/admin/sender-ids', [
            'name' => 'NDOA',
            'generic' => true,
            'available_to_all' => false,
        ])
        ->assertCreated()
        ->assertJsonPath('senderId.name', 'NDOA')
        ->assertJsonPath('senderId.availableToAll', false)
        ->assertJsonPath('senderId.source', SenderIdSource::Generic->value);

    $id = $create->json('senderId.id');

    $this->actingAs($user)
        ->getJson('/api/sender-ids')
        ->assertOk()
        ->assertJsonCount(0, 'senderIds');

    $this->actingAs($admin)
        ->postJson("/api/admin/sender-ids/{$id}/grants", [
            'customer_id' => $customer->id,
        ])
        ->assertOk()
        ->assertJsonPath('senderId.grants.0.id', $customer->id);

    $this->actingAs($user)
        ->getJson('/api/sender-ids')
        ->assertOk()
        ->assertJsonPath('senderIds.0.name', 'NDOA');

    $this->actingAs($otherUser)
        ->getJson('/api/sender-ids')
        ->assertOk()
        ->assertJsonCount(0, 'senderIds');

    $this->actingAs($otherUser)
        ->postJson('/api/sender-ids/assert', ['sender_id' => 'NDOA'])
        ->assertUnprocessable();
});

it('lets every customer use a catalog name marked available to all', function () {
    $admin = User::factory()->admin()->create();
    [$firstUser] = senderCustomer();
    [$secondUser] = senderCustomer();

    $this->actingAs($admin)
        ->postJson('/api/admin/sender-ids', [
            'name' => 'KIKAO',
            'generic' => true,
            'available_to_all' => true,
        ])
        ->assertCreated();

    $this->actingAs($firstUser)
        ->getJson('/api/sender-ids')
        ->assertOk()
        ->assertJsonPath('senderIds.0.name', 'KIKAO');

    $this->actingAs($secondUser)
        ->postJson('/api/sender-ids/assert', ['sender_id' => 'kikao'])
        ->assertOk()
        ->assertJsonPath('senderId.name', 'KIKAO');
});

it('lets an admin add a non-generic name and keep it off the public list', function () {
    $admin = User::factory()->admin()->create();
    [$user, $customer] = senderCustomer();

    $this->actingAs($admin)
        ->postJson('/api/admin/sender-ids', [
            'name' => 'SOSCOM',
            'generic' => false,
            'available_to_all' => false,
        ])
        ->assertCreated()
        ->assertJsonPath('senderId.source', SenderIdSource::Admin->value);

    $this->actingAs($user)
        ->getJson('/api/sender-ids')
        ->assertOk()
        ->assertJsonCount(0, 'senderIds');

    $id = SenderId::query()->where('name', 'SOSCOM')->value('id');

    $this->actingAs($admin)
        ->postJson("/api/admin/sender-ids/{$id}/grants", [
            'customer_id' => $customer->id,
        ])
        ->assertOk();

    $this->actingAs($user)
        ->getJson('/api/sender-ids')
        ->assertOk()
        ->assertJsonPath('senderIds.0.name', 'SOSCOM');
});

it('lets an admin add a catalog name that contains a space', function () {
    $admin = User::factory()->admin()->create();
    [$user] = senderCustomer();

    $this->actingAs($admin)
        ->postJson('/api/admin/sender-ids', [
            'name' => '  SEND   OFF ',
            'generic' => true,
            'available_to_all' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('senderId.name', 'SEND OFF');

    $this->actingAs($user)
        ->postJson('/api/sender-ids/assert', ['sender_id' => 'send off'])
        ->assertOk()
        ->assertJsonPath('senderId.name', 'SEND OFF');
});

it('rejects a catalog name with symbols', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->postJson('/api/admin/sender-ids', [
            'name' => 'SEND-OFF',
            'generic' => true,
            'available_to_all' => false,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

it('forbids customers from managing the sender id catalog', function () {
    [$user] = senderCustomer();

    $this->actingAs($user)
        ->postJson('/api/admin/sender-ids', [
            'name' => 'NDOA',
            'generic' => true,
            'available_to_all' => false,
        ])
        ->assertForbidden();
});
