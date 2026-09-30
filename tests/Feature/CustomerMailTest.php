<?php

use App\Enums\SenderIdRequestStatus;
use App\Models\Customer;
use App\Models\SenderIdRequest;
use App\Models\User;
use App\Notifications\PaymentConfirmedNotification;
use App\Notifications\PaymentRejectedNotification;
use App\Notifications\PaymentRequestedNotification;
use App\Notifications\SenderIdApprovedNotification;
use App\Notifications\SenderIdRejectedNotification;
use App\Notifications\LoginOtpNotification;
use App\Notifications\WelcomeCustomerNotification;
use Database\Seeders\PricingTierSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

it('emails a welcome message after a customer registers', function () {
    Notification::fake();

    $this->postJson('/api/register', [
        'name' => 'Neema Mushi',
        'email' => 'neema@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])
        ->assertCreated()
        ->assertJsonPath('requiresOtp', true);

    $user = User::query()->where('email', 'neema@example.com')->first();

    Notification::assertSentTo($user, WelcomeCustomerNotification::class, function (WelcomeCustomerNotification $notification) use ($user): bool {
        $mail = $notification->toMail($user);

        return $mail->subject === 'Welcome to Soscom SMS'
            && collect($mail->introLines)->contains(fn (string $line): bool => str_contains($line, '20 free SMS'));
    });
    Notification::assertSentTo($user, LoginOtpNotification::class);
});

it('emails payment requested, confirmed, and rejected notices', function () {
    Notification::fake();
    $this->seed(PricingTierSeeder::class);

    $user = User::factory()->create();
    $customer = Customer::factory()->complete()->create([
        'user_id' => $user->id,
        'sms_balance' => 9,
    ]);
    $admin = User::factory()->admin()->create();

    $create = $this->actingAs($user)
        ->postJson('/api/payments', [
            'quantity' => 100,
            'payer_reference' => '0754221890',
        ])
        ->assertCreated();

    Notification::assertSentTo($user, PaymentRequestedNotification::class);

    $this->actingAs($admin)
        ->postJson('/api/admin/payments/'.$create->json('payment.id').'/confirmation')
        ->assertOk();

    Notification::assertSentTo($user, PaymentConfirmedNotification::class);

    $second = $this->actingAs($user)
        ->postJson('/api/payments', [
            'quantity' => 100,
            'payer_reference' => '0754221890',
        ])
        ->assertCreated();

    $this->actingAs($admin)
        ->postJson('/api/admin/payments/'.$second->json('payment.id').'/rejection', [
            'rejection_reason' => 'No Lipa namba payment received.',
        ])
        ->assertOk();

    Notification::assertSentTo($user, PaymentRejectedNotification::class);
});

it('emails sender id approval and rejection', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $customer = Customer::factory()->complete()->create(['user_id' => $user->id]);

    $approved = SenderIdRequest::factory()->create([
        'customer_id' => $customer->id,
        'name' => 'SOSCOM',
        'status' => SenderIdRequestStatus::UnderReview,
    ]);

    $this->actingAs($admin)
        ->postJson("/api/admin/sender-id-requests/{$approved->id}/provider-submissions", [
            'submitted_to_provider_at' => '2026-09-20',
            'provider_reference' => 'email-41',
        ])
        ->assertOk();

    $this->actingAs($admin)
        ->postJson("/api/admin/sender-id-requests/{$approved->id}/approval")
        ->assertOk();

    Notification::assertSentTo($user, SenderIdApprovedNotification::class);

    $rejected = SenderIdRequest::factory()->submittedToProvider()->create([
        'customer_id' => $customer->id,
        'name' => 'REJECTME',
    ]);

    $this->actingAs($admin)
        ->postJson("/api/admin/sender-id-requests/{$rejected->id}/rejection", [
            'rejection_reason' => 'Mobishastra declined this name.',
        ])
        ->assertOk();

    Notification::assertSentTo($user, SenderIdRejectedNotification::class);
});

it('renders a branded mail layout with the soscomsms mark and logo', function () {
    $user = User::factory()->make(['name' => 'Amina Juma']);
    $html = (string) (new WelcomeCustomerNotification(20))->toMail($user)->render();

    expect($html)
        ->toContain('soscom')
        ->toContain('sms')
        ->toContain('#379019')
        ->toContain('soscom-sms-icon.png')
        ->toContain('Hi Amina,')
        ->and($html)->not->toContain('Laravel');
});

it('uses the branded password reset email', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'resetme@example.com', 'name' => 'Amina Juma']);

    $this->postJson('/api/forgot-password', [
        'email' => $user->email,
    ])->assertOk();

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
        $mail = $notification->toMail($user);

        return $mail->subject === 'Reset your Soscom SMS password'
            && collect($mail->introLines)->contains(fn (string $line): bool => str_contains($line, 'reset your password'));
    });
});
