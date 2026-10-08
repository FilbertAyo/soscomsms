<?php

use App\Models\User;
use App\Notifications\LoginOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function completeOtp(TestResponse $challengeResponse): TestResponse
{
    $email = $challengeResponse->json('email');
    $user = User::query()->where('email', $email)->firstOrFail();
    $code = null;

    Notification::assertSentTo(
        $user,
        LoginOtpNotification::class,
        function (LoginOtpNotification $notification) use (&$code): bool {
            $code = $notification->code;

            return true;
        }
    );

    return test()->postJson('/api/otp/verify', [
        'challenge' => $challengeResponse->json('challenge'),
        'otp' => $code,
    ]);
}

function senderIdPdf(string $contents = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n"): UploadedFile
{
    return UploadedFile::fake()->createWithContent('national-id.pdf', $contents);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function senderIdRequestPayload(array $overrides = []): array
{
    $payload = [
        'name' => 'linkadi',
        'purpose' => 'transactional',
        'organization' => 'Linkadi Ltd',
        'description' => 'Order updates',
        'sample_content' => 'Hi Amina, your order 1042 is ready for pickup.',
        'identity_document_type' => 'national_id',
    ];

    if (! array_key_exists('identity_document', $overrides)) {
        $payload['identity_document'] = senderIdPdf();
    }

    return array_merge($payload, $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function snippeCompletedEvent(string $reference, int $amount, int $paymentId, array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 'evt_'.$paymentId,
        'type' => 'payment.completed',
        'api_version' => '2026-01-25',
        'data' => [
            'reference' => $reference,
            'status' => 'completed',
            'amount' => ['value' => $amount, 'currency' => 'TZS'],
            'settlement' => [
                'gross' => ['value' => $amount, 'currency' => 'TZS'],
                'fees' => ['value' => 63, 'currency' => 'TZS'],
                'net' => ['value' => $amount - 63, 'currency' => 'TZS'],
            ],
            'channel' => ['type' => 'mobile_money', 'provider' => 'mpesa'],
            'metadata' => ['payment_id' => (string) $paymentId],
        ],
    ], $overrides);
}

function signInAs(User $user, string $password = 'password', bool $remember = false): TestResponse
{
    Notification::fake();

    $login = test()->postJson('/api/login', [
        'email' => $user->email,
        'password' => $password,
        'remember' => $remember,
    ]);

    $login->assertOk()->assertJsonPath('requiresOtp', true);

    return completeOtp($login);
}

function configureSnippe(): void
{
    config([
        'sms.snippe.api_key' => 'snp_test_key',
        'sms.snippe.webhook_secret' => 'whsec_test_secret',
        'sms.snippe.base_url' => 'https://api.snippe.sh',
    ]);
}

function fakeSnippePayment(string $reference = 'ref-pay-1'): void
{
    stubSnippe([
        'https://api.snippe.sh/v1/payments' => Http::response([
            'status' => 'success',
            'code' => 201,
            'data' => [
                'reference' => $reference,
                'status' => 'pending',
                'expires_at' => now()->addHours(4)->toIso8601String(),
            ],
        ], 201),
        'https://api.snippe.sh/*' => Http::response([
            'status' => 'success',
            'code' => 200,
            'data' => [
                'reference' => $reference,
                'status' => 'pending',
            ],
        ]),
    ]);
}

/**
 * @param  array<string, mixed>  $stubs
 */
function stubSnippe(array $stubs): void
{
    $factory = new Factory;
    Http::swap($factory);
    $factory->fake($stubs);
}

/**
 * @param  array<string, mixed>  $event
 * @param  array{secret?: string, timestamp?: int, signature?: string}  $overrides
 */
function postSnippeWebhook(array $event, array $overrides = []): TestResponse
{
    $secret = $overrides['secret'] ?? (string) config('sms.snippe.webhook_secret');
    $timestamp = $overrides['timestamp'] ?? time();
    $body = json_encode($event, JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        throw new RuntimeException('Could not encode the Snippe webhook.');
    }

    $signature = $overrides['signature'] ?? hash_hmac('sha256', $timestamp.'.'.$body, $secret);

    return test()->call(
        'POST',
        '/api/webhooks/snippe',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
            'HTTP_X_WEBHOOK_EVENT' => (string) ($event['type'] ?? ''),
        ],
        $body,
    );
}
