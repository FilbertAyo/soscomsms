<?php

use App\Models\ApiLog;
use App\Models\Customer;
use App\Models\SenderId;
use App\Models\User;
use App\Services\ApiKeyService;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

it('adds a code and request id to unauthenticated json', function () {
    $response = $this->withHeaders(['X-Request-Id' => 'spa-req-123456'])
        ->getJson('/api/me');

    $response->assertUnauthorized()
        ->assertJsonPath('code', 'UNAUTHORIZED')
        ->assertJsonPath('request_id', 'spa-req-123456')
        ->assertHeader('X-Request-Id', 'spa-req-123456')
        ->assertJsonStructure(['message', 'code', 'request_id']);
});

it('adds a validation error code without dropping field errors', function () {
    $this->postJson('/api/login', [])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['message', 'errors', 'code', 'request_id']);
});

it('returns a generic not found body for missing session resources', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->complete()->create(['user_id' => $user->id]);
    expect($customer)->not->toBeNull();

    signInAs($user);

    $this->getJson('/api/campaigns/999999')
        ->assertNotFound()
        ->assertJsonPath('message', "We couldn't find that.")
        ->assertJsonPath('code', 'NOT_FOUND')
        ->assertJsonMissingPath('exception')
        ->assertJsonStructure(['message', 'code', 'request_id']);
});

it('does not expose unexpected exception details in json', function () {
    Route::middleware('web')->get('/api/__test-boom', function () {
        throw new RuntimeException('SQLSTATE[HY000] /var/www/secret.php PDOException');
    });

    $events = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$events): void {
        $events[] = $event;
    });

    $response = $this->withHeaders(['X-Request-Id' => 'boom-req-999999'])
        ->getJson('/api/__test-boom');

    $response->assertStatus(500)
        ->assertJsonPath('message', 'Something went wrong. Please try again.')
        ->assertJsonPath('code', 'SERVER_ERROR')
        ->assertJsonPath('request_id', 'boom-req-999999')
        ->assertHeader('X-Request-Id', 'boom-req-999999');

    expect($response->getContent())
        ->not->toContain('SQLSTATE')
        ->not->toContain('/var/www')
        ->not->toContain('PDOException')
        ->not->toContain('RuntimeException');

    $logged = collect($events)->first(
        fn (MessageLogged $event): bool => isset($event->context['exception'])
            || str_contains($event->message, 'RuntimeException')
    );

    expect($logged)->not->toBeNull()
        ->and($logged->context['request_id'] ?? null)->toBe('boom-req-999999');
});

it('echoes request ids on the public api and logs validation as 422', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->complete()->create(['user_id' => $user->id]);
    SenderId::factory()->generic()->create();
    $plain = app(ApiKeyService::class)->create($customer, 'Error handling')['plaintext'];

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$plain,
        'X-Request-Id' => 'pub-api-req-1',
    ])->postJson('/api/v1/sms/send', []);

    $response->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonPath('request_id', 'pub-api-req-1')
        ->assertHeader('X-Request-Id', 'pub-api-req-1');

    expect(ApiLog::query()->latest('id')->value('status_code'))->toBe(422)
        ->and(ApiLog::query()->latest('id')->value('request_id'))->toBe('pub-api-req-1');
});

it('keeps public api key error codes', function () {
    $this->withHeaders(['Authorization' => 'Bearer not-a-real-key'])
        ->getJson('/api/v1/balance')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'invalid_api_key')
        ->assertJsonStructure(['message', 'code', 'request_id']);
});

it('keeps authored forbidden messages for customers', function () {
    $user = User::factory()->create();
    Customer::factory()->complete()->create(['user_id' => $user->id]);
    signInAs($user);

    $this->getJson('/api/admin/overview')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN')
        ->assertJsonPath('message', 'This area is for Soscom administrators.')
        ->assertJsonStructure(['message', 'code', 'request_id']);
});

it('keeps incomplete profile forbidden copy', function () {
    $user = User::factory()->create();
    Customer::factory()->create(['user_id' => $user->id]);
    signInAs($user);

    $this->getJson('/api/campaigns')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN')
        ->assertJsonPath(
            'message',
            'Complete your business name and Tanzanian phone number before using this feature.',
        )
        ->assertJsonStructure(['message', 'code', 'request_id']);
});

it('adds a rate limited code and retry after', function () {
    Route::middleware(['web', 'throttle:1,1'])->get('/api/__test-throttle', fn () => ['ok' => true]);

    $this->getJson('/api/__test-throttle')->assertOk();

    $this->getJson('/api/__test-throttle')
        ->assertStatus(429)
        ->assertJsonPath('code', 'RATE_LIMITED')
        ->assertHeader('Retry-After')
        ->assertJsonStructure(['message', 'code', 'request_id']);
});
