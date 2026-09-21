<?php

use App\Enums\CustomerStatus;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Models\Customer;
use App\Models\User;
use App\Support\TanzaniaPhone;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

it('registers a customer with a pending profile and signs them in after otp', function () {
    Notification::fake();

    $challenge = $this->postJson('/api/register', [
        'name' => 'Neema Mushi',
        'email' => 'neema@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])
        ->assertCreated()
        ->assertJsonPath('requiresOtp', true)
        ->assertJsonPath('email', 'neema@example.com');

    completeOtp($challenge)
        ->assertOk()
        ->assertJsonPath('user.email', 'neema@example.com')
        ->assertJsonPath('user.role', 'customer')
        ->assertJsonPath('user.customer.status', CustomerStatus::PendingProfile->value)
        ->assertJsonPath('user.customer.smsBalance', 20);

    $this->assertAuthenticated();

    $user = User::query()->where('email', 'neema@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user?->customer)->not->toBeNull()
        ->and($user?->customer?->status)->toBe(CustomerStatus::PendingProfile)
        ->and($user?->customer?->sms_balance)->toBe(20)
        ->and($user?->customer?->creditTransactions()->count())->toBe(1);
});

it('rejects duplicate registration emails', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/register', [
        'name' => 'Someone Else',
        'email' => 'taken@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertUnprocessable();
});

it('completes a customer profile with a Tanzanian phone number', function () {
    $user = User::factory()->create(['role' => 'customer']);
    Customer::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->patchJson('/api/profile', [
            'business_name' => 'Linkadi Ltd',
            'phone' => '0754 221 890',
            'job_title' => 'Owner',
        ])
        ->assertOk()
        ->assertJsonPath('user.customer.businessName', 'Linkadi Ltd')
        ->assertJsonPath('user.customer.phone', '+255754221890')
        ->assertJsonPath('user.customer.status', CustomerStatus::Active->value);

    expect($user->fresh()?->hasCompleteProfile())->toBeTrue();
});

it('updates a job title after a real login', function () {
    $user = User::factory()->create(['role' => 'customer']);
    Customer::factory()->complete()->create(['user_id' => $user->id]);

    $this->withHeaders([
        'Origin' => 'http://127.0.0.1:8000',
        'Referer' => 'http://127.0.0.1:8000/sign-in',
    ]);

    signInAs($user)->assertOk();

    $this->withHeaders([
        'Origin' => 'http://127.0.0.1:8000',
        'Referer' => 'http://127.0.0.1:8000/settings/business',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->patchJson('/api/profile', [
        'job_title' => 'Director',
    ])
        ->assertOk()
        ->assertJsonPath('user.customer.jobTitle', 'Director')
        ->assertJsonStructure(['user', 'csrf_token']);
});

it('rejects a non-Tanzanian phone number', function () {
    $user = User::factory()->create(['role' => 'customer']);
    Customer::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->patchJson('/api/profile', [
            'phone' => '+254712345678',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');
});

it('updates the account name and email', function () {
    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
        'role' => 'customer',
    ]);
    Customer::factory()->complete()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->patchJson('/api/profile', [
            'name' => 'Amina Juma',
            'email' => 'amina@example.com',
        ])
        ->assertOk()
        ->assertJsonPath('user.name', 'Amina Juma')
        ->assertJsonPath('user.email', 'amina@example.com');
});

it('changes the password when the current password is correct', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/api/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertOk();

    expect(Hash::check('new-password', $user->fresh()?->password))->toBeTrue();
});

it('rejects a password change with the wrong current password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/api/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertUnprocessable();
});

it('sends a password reset link and resets the password', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'resetme@example.com']);

    $this->postJson('/api/forgot-password', [
        'email' => $user->email,
    ])->assertOk();

    $token = null;
    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    expect($token)->not->toBeNull();

    $this->postJson('/api/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'reset-password',
        'password_confirmation' => 'reset-password',
    ])->assertOk();

    expect(Hash::check('reset-password', $user->fresh()?->password))->toBeTrue();
});

it('does not reveal whether an email exists when requesting a reset', function () {
    Notification::fake();

    $this->postJson('/api/forgot-password', [
        'email' => 'nobody@example.com',
    ])->assertOk();

    Notification::assertNothingSent();
});

it('forbids customers from admin-only actions', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $admin = User::factory()->admin()->create();
    $middleware = new EnsureUserIsAdmin;

    $customerRequest = Request::create('/api/admin/overview', 'GET');
    $customerRequest->setUserResolver(fn () => $customer);
    $customerResponse = $middleware->handle($customerRequest, fn () => response('ok'));
    expect($customerResponse->getStatusCode())->toBe(403);

    $adminRequest = Request::create('/api/admin/overview', 'GET');
    $adminRequest->setUserResolver(fn () => $admin);
    $adminResponse = $middleware->handle($adminRequest, fn () => response('ok'));
    expect($adminResponse->getStatusCode())->toBe(200);
});

it('normalizes Tanzanian mobile numbers', function (string $input, string $expected) {
    expect(TanzaniaPhone::normalize($input))->toBe($expected)
        ->and(TanzaniaPhone::isValid($input))->toBeTrue();
})->with([
    ['+255754221890', '+255754221890'],
    ['0754221890', '+255754221890'],
    ['754221890', '+255754221890'],
    ['255754221890', '+255754221890'],
    ['+255 612 000 111', '+255612000111'],
]);

it('rejects invalid Tanzanian numbers', function (string $input) {
    expect(TanzaniaPhone::isValid($input))->toBeFalse();
})->with([
    ['+254712345678'],
    ['+255154221890'],
    ['12345'],
    [''],
]);
