<?php

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\User;
use App\Notifications\CompleteProfileReminderNotification;
use Illuminate\Support\Facades\Notification;

function pendingCustomer(array $attributes = []): Customer
{
    $user = User::factory()->create();

    return Customer::factory()->create([
        'user_id' => $user->id,
        'sms_balance' => 20,
        'created_at' => now(),
        ...$attributes,
    ]);
}

function remindersSentTo(Customer $customer): int
{
    return Notification::sent($customer->user, CompleteProfileReminderNotification::class)->count();
}

it('sends reminders on day 1, 3 and 7 and then stops', function () {
    Notification::fake();
    $customer = pendingCustomer();

    $this->artisan('customers:remind-incomplete-profiles')->assertSuccessful();
    expect(remindersSentTo($customer))->toBe(0);

    $expected = [1 => 1, 2 => 1, 3 => 2, 4 => 2, 7 => 3, 8 => 3, 30 => 3];
    $day = 0;

    foreach ($expected as $targetDay => $count) {
        $this->travel($targetDay - $day)->days();
        $day = $targetDay;

        $this->artisan('customers:remind-incomplete-profiles')->assertSuccessful();

        expect(remindersSentTo($customer))->toBe($count, "day {$targetDay}");
    }

    $steps = Notification::sent($customer->user, CompleteProfileReminderNotification::class)
        ->map(fn (CompleteProfileReminderNotification $n) => $n->step)
        ->all();

    expect($steps)->toBe([1, 2, 3])
        ->and($customer->fresh()->profile_reminders_sent)->toBe(3);
});

it('does not send twice on the same day', function () {
    Notification::fake();
    $customer = pendingCustomer(['created_at' => now()->subDays(30)]);

    $this->artisan('customers:remind-incomplete-profiles')->assertSuccessful();
    $this->artisan('customers:remind-incomplete-profiles')->assertSuccessful();

    expect(remindersSentTo($customer))->toBe(1);
});

it('spaces out reminders for people who signed up long ago', function () {
    Notification::fake();
    $customer = pendingCustomer(['created_at' => now()->subDays(60)]);

    foreach (range(1, 7) as $day) {
        $this->artisan('customers:remind-incomplete-profiles')->assertSuccessful();
        $this->travel(1)->days();
    }

    expect(remindersSentTo($customer))->toBe(3);
});

it('stops reminding once the profile is complete', function () {
    Notification::fake();
    $customer = pendingCustomer(['created_at' => now()->subDays(2)]);

    $this->artisan('customers:remind-incomplete-profiles')->assertSuccessful();
    expect(remindersSentTo($customer))->toBe(1);

    $customer->forceFill([
        'business_name' => 'Linkadi Ltd',
        'phone' => '+255754221890',
        'status' => CustomerStatus::Active,
    ])->save();

    $this->travel(10)->days();
    $this->artisan('customers:remind-incomplete-profiles')->assertSuccessful();

    expect(remindersSentTo($customer))->toBe(1);
});

it('never reminds suspended customers or admins', function () {
    Notification::fake();
    $suspended = pendingCustomer([
        'created_at' => now()->subDays(10),
        'status' => CustomerStatus::Suspended,
    ]);
    $admin = User::factory()->admin()->create();
    $adminCustomer = Customer::factory()->create([
        'user_id' => $admin->id,
        'created_at' => now()->subDays(10),
    ]);

    $this->artisan('customers:remind-incomplete-profiles')->assertSuccessful();

    expect(remindersSentTo($suspended))->toBe(0)
        ->and(remindersSentTo($adminCustomer))->toBe(0);
});
