<?php

use App\Models\User;
use App\Notifications\LoginOtpNotification;
use Illuminate\Support\Facades\Notification;

it('returns a successful response for the SPA', function () {
    $this->get('/')->assertOk();
});

it('reports application health', function () {
    $this->get('/up')->assertOk();
});

it('redirects legacy livewire login to the SPA sign-in', function () {
    $this->get('/login')->assertRedirect('/sign-in');
});

it('redirects legacy livewire register to the SPA sign-up', function () {
    $this->get('/register')->assertRedirect('/sign-up');
});

it('rejects unauthenticated me requests', function () {
    $this->getJson('/api/me')->assertUnauthorized();
});

it('signs in with a valid email code and returns the current user', function () {
    $user = User::factory()->create([
        'email' => 'customer@example.com',
        'role' => 'customer',
    ]);

    signInAs($user)
        ->assertOk()
        ->assertJsonPath('user.email', $user->email)
        ->assertJsonPath('user.role', 'customer')
        ->assertJsonStructure(['user', 'csrf_token']);

    $this->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.role', 'customer')
        ->assertJsonStructure(['user', 'csrf_token']);
});

it('keeps the session after visiting the landing page like a browser', function () {
    $user = User::factory()->create([
        'email' => 'still-logged-in@example.com',
        'role' => 'customer',
    ]);

    $this->withHeaders([
        'Origin' => 'http://127.0.0.1:8000',
        'Referer' => 'http://127.0.0.1:8000/sign-in',
    ]);

    signInAs($user)->assertOk();

    $this->withHeaders([
        'Origin' => 'http://127.0.0.1:8000',
        'Referer' => 'http://127.0.0.1:8000/',
    ])->get('/')->assertOk();

    $this->withHeaders([
        'Origin' => 'http://127.0.0.1:8000',
        'Referer' => 'http://127.0.0.1:8000/',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('user.email', $user->email);
});

it('sets a 30-day remember cookie when asked', function () {
    $user = User::factory()->create();

    $response = signInAs($user, remember: true)->assertOk();

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($item): bool => str_starts_with($item->getName(), 'remember_web_'));

    expect($cookie)->not->toBeNull()
        ->and($cookie?->getExpiresTime())->toBeGreaterThan(now()->addDays(29)->timestamp);
});

it('rejects an invalid email code', function () {
    Notification::fake();
    $user = User::factory()->create();

    $login = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk();

    $this->postJson('/api/otp/verify', [
        'challenge' => $login->json('challenge'),
        'otp' => '000000',
    ])->assertUnprocessable();

    $this->assertGuest();
    Notification::assertSentTo($user, LoginOtpNotification::class);
});

it('rejects invalid login credentials', function () {
    $user = User::factory()->create();

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertUnprocessable();

    $this->assertGuest();
});

it('signs out and rejects subsequent me requests', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->postJson('/api/logout')
        ->assertOk();

    $this->assertGuest();
    $this->getJson('/api/me')->assertUnauthorized();
});
