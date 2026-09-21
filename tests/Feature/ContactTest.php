<?php

use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Customer;
use App\Models\User;
use App\Support\TanzaniaPhone;
use Illuminate\Http\UploadedFile;

function customerUser(): array
{
    $user = User::factory()->create();
    $customer = Customer::factory()->complete()->create(['user_id' => $user->id]);

    return [$user, $customer];
}

it('normalizes a local Tanzanian mobile to E.164', function () {
    expect(TanzaniaPhone::normalize('0712 445 221'))->toBe('+255712445221')
        ->and(TanzaniaPhone::isValid('0712 445 221'))->toBeTrue()
        ->and(TanzaniaPhone::isValid('+254712345678'))->toBeFalse();
});

it('creates a group and a contact with a normalized Tanzanian phone', function () {
    [$user, $customer] = customerUser();

    $this->actingAs($user)
        ->postJson('/api/contact-groups', [
            'name' => 'Parents',
            'description' => 'School parents',
        ])
        ->assertCreated()
        ->assertJsonPath('group.name', 'Parents')
        ->assertJsonPath('group.contactsCount', 0);

    $groupId = $customer->contactGroups()->where('name', 'Parents')->value('id');

    $this->actingAs($user)
        ->postJson('/api/contacts', [
            'name' => 'Juma Hassan',
            'phone' => '0712 445 221',
            'contact_group_id' => $groupId,
        ])
        ->assertCreated()
        ->assertJsonPath('contact.name', 'Juma Hassan')
        ->assertJsonPath('contact.phone', '+255712445221')
        ->assertJsonPath('contact.groupName', 'Parents')
        ->assertJsonPath('contact.status', ContactStatus::Active->value);

    $this->assertDatabaseHas('contacts', [
        'customer_id' => $customer->id,
        'phone' => '+255712445221',
        'contact_group_id' => $groupId,
    ]);
});

it('rejects a non-Tanzanian phone number', function () {
    [$user] = customerUser();

    $this->actingAs($user)
        ->postJson('/api/contacts', [
            'name' => 'Kenyan Number',
            'phone' => '+254712345678',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');
});

it('rejects a duplicate phone for the same customer', function () {
    [$user, $customer] = customerUser();
    Contact::factory()->create([
        'customer_id' => $customer->id,
        'phone' => '+255712445221',
    ]);

    $this->actingAs($user)
        ->postJson('/api/contacts', [
            'name' => 'Juma Hassan',
            'phone' => '0712 445 221',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');
});

it('lets two customers keep the same phone number', function () {
    [$firstUser, $firstCustomer] = customerUser();
    [$secondUser] = customerUser();

    Contact::factory()->create([
        'customer_id' => $firstCustomer->id,
        'phone' => '+255712445221',
        'name' => 'Amina Contact',
    ]);

    $this->actingAs($secondUser)
        ->postJson('/api/contacts', [
            'name' => 'Juma Hassan',
            'phone' => '0712 445 221',
        ])
        ->assertCreated()
        ->assertJsonPath('contact.phone', '+255712445221');
});

it('hides another customer contacts and groups', function () {
    [$owner] = customerUser();
    [$stranger] = customerUser();

    $this->actingAs($owner)
        ->postJson('/api/contact-groups', ['name' => 'Parents'])
        ->assertCreated();

    $group = $owner->customer->contactGroups()->first();

    $this->actingAs($owner)
        ->postJson('/api/contacts', [
            'name' => 'Juma Hassan',
            'phone' => '0712 445 221',
            'contact_group_id' => $group->id,
        ])
        ->assertCreated();

    $contact = $owner->customer->contacts()->first();

    $this->actingAs($stranger)
        ->getJson('/api/contacts')
        ->assertOk()
        ->assertJsonCount(0, 'contacts');

    $this->actingAs($stranger)
        ->getJson('/api/contact-groups')
        ->assertOk()
        ->assertJsonCount(0, 'groups');

    $this->actingAs($stranger)
        ->patchJson("/api/contacts/{$contact->id}", ['name' => 'Hacked'])
        ->assertNotFound();

    $this->actingAs($stranger)
        ->deleteJson("/api/contact-groups/{$group->id}")
        ->assertNotFound();
});

it('deactivates a contact without deleting it', function () {
    [$user, $customer] = customerUser();
    $contact = Contact::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($user)
        ->patchJson("/api/contacts/{$contact->id}", [
            'status' => ContactStatus::Inactive->value,
        ])
        ->assertOk()
        ->assertJsonPath('contact.status', ContactStatus::Inactive->value);

    expect($contact->fresh()?->status)->toBe(ContactStatus::Inactive)
        ->and(Contact::query()->count())->toBe(1);
});

it('filters contacts by search, group, and status', function () {
    [$user, $customer] = customerUser();
    $parents = ContactGroup::factory()->create([
        'customer_id' => $customer->id,
        'name' => 'Parents',
    ]);
    $staff = ContactGroup::factory()->create([
        'customer_id' => $customer->id,
        'name' => 'Staff',
    ]);

    Contact::factory()->create([
        'customer_id' => $customer->id,
        'contact_group_id' => $parents->id,
        'name' => 'Juma Hassan',
        'phone' => '+255712445221',
        'status' => ContactStatus::Active,
    ]);
    Contact::factory()->create([
        'customer_id' => $customer->id,
        'contact_group_id' => $staff->id,
        'name' => 'Neema Mushi',
        'phone' => '+255754889102',
        'status' => ContactStatus::Inactive,
    ]);

    $this->actingAs($user)
        ->getJson('/api/contacts?q=Juma')
        ->assertOk()
        ->assertJsonCount(1, 'contacts')
        ->assertJsonPath('contacts.0.name', 'Juma Hassan');

    $this->actingAs($user)
        ->getJson('/api/contacts?q=Parents')
        ->assertOk()
        ->assertJsonCount(1, 'contacts')
        ->assertJsonPath('contacts.0.groupName', 'Parents');

    $this->actingAs($user)
        ->getJson("/api/contacts?group_id={$parents->id}")
        ->assertOk()
        ->assertJsonCount(1, 'contacts');

    $this->actingAs($user)
        ->getJson('/api/contacts?status=inactive')
        ->assertOk()
        ->assertJsonCount(1, 'contacts')
        ->assertJsonPath('contacts.0.name', 'Neema Mushi');
});

it('ungroups contacts when a group is deleted', function () {
    [$user, $customer] = customerUser();
    $group = ContactGroup::factory()->create([
        'customer_id' => $customer->id,
        'name' => 'Parents',
    ]);
    $contact = Contact::factory()->create([
        'customer_id' => $customer->id,
        'contact_group_id' => $group->id,
    ]);

    $this->actingAs($user)
        ->deleteJson("/api/contact-groups/{$group->id}")
        ->assertOk();

    expect($contact->fresh()?->contact_group_id)->toBeNull()
        ->and(Contact::query()->count())->toBe(1)
        ->and(ContactGroup::query()->count())->toBe(0);
});

it('imports a csv with created skipped and invalid counts', function () {
    [$user, $customer] = customerUser();
    Contact::factory()->create([
        'customer_id' => $customer->id,
        'name' => 'Existing Neema',
        'phone' => '+255754889102',
    ]);

    $csv = <<<'CSV'
Name,Phone,Group
Juma Hassan,0712 445 221,Parents
Neema Mushi,0754 889 102,Customers
Juma Duplicate,0712445221,Parents
,07540000000,Parents
Grace Kimaro,+254712345678,Parents
CSV;

    $file = UploadedFile::fake()->createWithContent('contacts.csv', $csv);

    $this->actingAs($user)
        ->post('/api/contacts/import', [
            'file' => $file,
            'name_column' => 'Name',
            'phone_column' => 'Phone',
            'group_column' => 'Group',
        ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('created', 1)
        ->assertJsonPath('skipped', 2)
        ->assertJsonPath('invalid', 2);

    expect($customer->contacts()->count())->toBe(2)
        ->and($customer->contactGroups()->where('name', 'Parents')->exists())->toBeTrue()
        ->and($customer->contacts()->where('phone', '+255712445221')->value('name'))->toBe('Juma Hassan');
});

it('paginates contacts ten at a time', function () {
    [$user, $customer] = customerUser();
    Contact::factory()->count(12)->create(['customer_id' => $customer->id]);

    $this->actingAs($user)
        ->getJson('/api/contacts')
        ->assertOk()
        ->assertJsonCount(10, 'contacts')
        ->assertJsonPath('meta.currentPage', 1)
        ->assertJsonPath('meta.lastPage', 2)
        ->assertJsonPath('meta.perPage', 10)
        ->assertJsonPath('meta.total', 12);

    $this->actingAs($user)
        ->getJson('/api/contacts?page=2')
        ->assertOk()
        ->assertJsonCount(2, 'contacts')
        ->assertJsonPath('meta.currentPage', 2);
});
