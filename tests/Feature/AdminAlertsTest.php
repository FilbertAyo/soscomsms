<?php

use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\PaymentStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\Admin\NewUserRegisteredNotification;
use App\Notifications\Admin\PaymentAwaitingConfirmationNotification;
use App\Notifications\Admin\PaymentsExpiredNotification;
use App\Notifications\Admin\ProfileCompletedNotification;
use App\Notifications\Admin\SenderIdRequestedNotification;
use App\Notifications\Admin\TechnicalIssueNotification;
use App\Support\AdminAlerts;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Notification;

function alertsCustomer(bool $complete = true): array
{
    $user = User::factory()->create();
    $factory = Customer::factory();
    $customer = ($complete ? $factory->complete() : $factory)->create([
        'user_id' => $user->id,
        'sms_balance' => 20,
    ]);

    return [$user, $customer];
}

it('emails every admin when a user registers', function () {
    Notification::fake();
    $admins = User::factory()->admin()->count(2)->create();

    $this->postJson('/api/register', [
        'name' => 'Neema Mushi',
        'email' => 'neema@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertCreated();

    $newUser = User::query()->where('email', 'neema@example.com')->firstOrFail();

    foreach ($admins as $admin) {
        Notification::assertSentTo(
            $admin,
            NewUserRegisteredNotification::class,
            fn (NewUserRegisteredNotification $n) => $n->user->is($newUser),
        );
    }
    Notification::assertNotSentTo($newUser, NewUserRegisteredNotification::class);
});

it('emails admins only when a profile becomes complete', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    [$user] = alertsCustomer(complete: false);

    $this->actingAs($user)
        ->patchJson('/api/profile', ['business_name' => 'Linkadi Ltd'])
        ->assertOk();

    Notification::assertNotSentTo($admin, ProfileCompletedNotification::class);

    $this->actingAs($user)
        ->patchJson('/api/profile', ['phone' => '0754 221 890'])
        ->assertOk();

    Notification::assertSentToTimes($admin, ProfileCompletedNotification::class, 1);

    $this->actingAs($user)
        ->patchJson('/api/profile', ['job_title' => 'Owner'])
        ->assertOk();

    Notification::assertSentToTimes($admin, ProfileCompletedNotification::class, 1);
});

it('emails admins about a new Sender ID request', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    [$user] = alertsCustomer();

    $this->actingAs($user)
        ->post('/api/sender-id-requests', senderIdRequestPayload())
        ->assertCreated();

    Notification::assertSentTo($admin, SenderIdRequestedNotification::class);
    Notification::assertNotSentTo($user, SenderIdRequestedNotification::class);
});

it('emails admins when a payment needs confirming', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    [$user] = alertsCustomer();

    $this->actingAs($user)
        ->postJson('/api/payments', [
            'quantity' => 100,
            'payer_reference' => '0754221890',
        ])
        ->assertCreated();

    Notification::assertSentTo(
        $admin,
        PaymentAwaitingConfirmationNotification::class,
        fn (PaymentAwaitingConfirmationNotification $n) => $n->payment->payer_reference === '0754221890',
    );
});

it('sends one summary email when pending payments expire', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    [, $customer] = alertsCustomer();

    Payment::factory()->count(2)->create([
        'customer_id' => $customer->id,
        'status' => PaymentStatus::Pending,
        'expires_at' => now()->subHour(),
    ]);

    $this->artisan('payments:expire')->assertSuccessful();

    Notification::assertSentToTimes($admin, PaymentsExpiredNotification::class, 1);
    Notification::assertSentTo(
        $admin,
        PaymentsExpiredNotification::class,
        fn (PaymentsExpiredNotification $n) => $n->payments->count() === 2,
    );

    $this->artisan('payments:expire')->assertSuccessful();

    Notification::assertSentToTimes($admin, PaymentsExpiredNotification::class, 1);
});

it('sends at most one technical alert per issue per hour', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();

    AdminAlerts::technical('backup-failed', 'Database backup failed', 'Disk full.');
    AdminAlerts::technical('backup-failed', 'Database backup failed', 'Disk full.');
    AdminAlerts::technical('provider-balance-unreadable', 'Provider balance could not be read', 'Timeout.');

    Notification::assertSentToTimes($admin, TechnicalIssueNotification::class, 2);

    $this->travel(61)->minutes();
    AdminAlerts::technical('backup-failed', 'Database backup failed', 'Disk full.');

    Notification::assertSentToTimes($admin, TechnicalIssueNotification::class, 3);
});

it('alerts admins when a background job fails', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();

    $job = Mockery::mock(Job::class);
    $job->shouldReceive('resolveName')->andReturn('App\\Jobs\\SendCampaignBatch');
    $job->shouldReceive('getQueue')->andReturn('default');

    event(new JobFailed('database', $job, new RuntimeException('Gateway timed out')));

    Notification::assertSentTo(
        $admin,
        TechnicalIssueNotification::class,
        fn (TechnicalIssueNotification $n) => str_contains($n->details, 'SendCampaignBatch')
            && str_contains($n->details, 'Gateway timed out'),
    );
});

it('alerts admins once when a campaign fails completely', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $campaign = Campaign::factory()->create(['status' => CampaignStatus::Sending]);
    CampaignRecipient::factory()->count(2)->create([
        'campaign_id' => $campaign->id,
        'status' => CampaignRecipientStatus::Failed,
    ]);

    $campaign->refreshCounts();
    $campaign->refreshCounts();

    expect($campaign->status)->toBe(CampaignStatus::Failed);
    Notification::assertSentToTimes($admin, TechnicalIssueNotification::class, 1);
});
