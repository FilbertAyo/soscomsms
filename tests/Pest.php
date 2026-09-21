<?php

use App\Models\User;
use App\Notifications\LoginOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
