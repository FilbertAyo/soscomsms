<?php

use App\Models\Customer;
use App\Models\MessageTemplate;
use App\Models\User;

function templateCustomer(): array
{
    $user = User::factory()->create();
    $customer = Customer::factory()->complete()->create(['user_id' => $user->id]);

    return [$user, $customer];
}

it('stores character and sms counts when a template is created', function () {
    [$user, $customer] = templateCustomer();

    $this->actingAs($user)
        ->postJson('/api/message-templates', [
            'name' => 'Fee reminder',
            'body' => 'Hello',
        ])
        ->assertCreated()
        ->assertJsonPath('template.name', 'Fee reminder')
        ->assertJsonPath('template.body', 'Hello')
        ->assertJsonPath('template.encoding', 'gsm')
        ->assertJsonPath('template.characterCount', 5)
        ->assertJsonPath('template.segmentCount', 1);

    $this->actingAs($user)
        ->postJson('/api/message-templates', [
            'name' => 'Long notice',
            'body' => str_repeat('A', 161),
        ])
        ->assertCreated()
        ->assertJsonPath('template.characterCount', 161)
        ->assertJsonPath('template.segmentCount', 2);

    $this->actingAs($user)
        ->postJson('/api/message-templates', [
            'name' => 'Emoji note',
            'body' => 'Habari 🎉',
        ])
        ->assertCreated()
        ->assertJsonPath('template.encoding', 'unicode')
        ->assertJsonPath('template.segmentCount', 1);

    expect($customer->messageTemplates()->count())->toBe(3);
});

it('lists only the signed-in customer templates', function () {
    [$owner] = templateCustomer();
    [$stranger, $strangerCustomer] = templateCustomer();

    $this->actingAs($owner)
        ->postJson('/api/message-templates', [
            'name' => 'Parents',
            'body' => 'Meeting at 4pm.',
        ])
        ->assertCreated();

    MessageTemplate::factory()->create([
        'customer_id' => $strangerCustomer->id,
        'name' => 'Private',
        'body' => 'Do not share.',
    ]);

    $this->actingAs($owner)
        ->getJson('/api/message-templates')
        ->assertOk()
        ->assertJsonCount(1, 'templates')
        ->assertJsonPath('templates.0.name', 'Parents');

    $this->actingAs($stranger)
        ->getJson('/api/message-templates')
        ->assertOk()
        ->assertJsonCount(1, 'templates')
        ->assertJsonPath('templates.0.name', 'Private');
});

it('stops another customer from updating or deleting a template', function () {
    [$owner] = templateCustomer();
    [$stranger] = templateCustomer();

    $this->actingAs($owner)
        ->postJson('/api/message-templates', [
            'name' => 'Parents',
            'body' => 'Meeting at 4pm.',
        ])
        ->assertCreated();

    $template = $owner->customer->messageTemplates()->first();

    $this->actingAs($stranger)
        ->patchJson("/api/message-templates/{$template->id}", [
            'name' => 'Hacked',
            'body' => 'Changed.',
        ])
        ->assertNotFound();

    $this->actingAs($stranger)
        ->deleteJson("/api/message-templates/{$template->id}")
        ->assertNotFound();

    $this->assertDatabaseHas('message_templates', [
        'id' => $template->id,
        'name' => 'Parents',
        'body' => 'Meeting at 4pm.',
    ]);
});

it('rejects a duplicate template name for the same customer', function () {
    [$user] = templateCustomer();

    $this->actingAs($user)
        ->postJson('/api/message-templates', [
            'name' => 'Parents',
            'body' => 'First copy.',
        ])
        ->assertCreated();

    $this->actingAs($user)
        ->postJson('/api/message-templates', [
            'name' => 'Parents',
            'body' => 'Second copy.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});
