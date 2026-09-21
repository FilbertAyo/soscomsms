<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\LoginOtpNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthOtpService
{
    public function rememberSelfLogout(User $user): void
    {
        $minutes = $this->skipAfterLogoutMinutes();

        if ($minutes < 1) {
            return;
        }

        Cache::put($this->skipKey($user), true, now()->addMinutes($minutes));
    }

    public function canSkip(User $user): bool
    {
        return $this->skipAfterLogoutMinutes() >= 1
            && Cache::has($this->skipKey($user));
    }

    public function issue(User $user, bool $remember = false): array
    {
        $minutes = $this->expiresMinutes();
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $challenge = Str::random(40);

        Cache::put($this->key($challenge), [
            'user_id' => $user->id,
            'email' => $user->email,
            'remember' => $remember,
            'hash' => hash('sha256', $code),
            'attempts' => 0,
        ], now()->addMinutes($minutes));

        $user->notify(new LoginOtpNotification($code, $minutes));

        return [
            'requiresOtp' => true,
            'challenge' => $challenge,
            'email' => $user->email,
            'expiresIn' => $minutes * 60,
        ];
    }

    public function resend(string $challenge): array
    {
        $payload = $this->payload($challenge);
        Cache::forget($this->key($challenge));

        $user = User::query()->find($payload['user_id']);
        if ($user === null) {
            throw $this->expired();
        }

        return $this->issue($user, (bool) $payload['remember']);
    }

    /**
     * @return array{user: User, remember: bool}
     */
    public function consume(string $challenge, string $otp): array
    {
        $payload = $this->payload($challenge);
        $maxAttempts = (int) config('auth.otp.max_attempts', 5);

        if ((int) $payload['attempts'] >= $maxAttempts) {
            Cache::forget($this->key($challenge));

            throw ValidationException::withMessages([
                'otp' => 'Too many incorrect codes. Sign in again.',
            ]);
        }

        if (! hash_equals((string) $payload['hash'], hash('sha256', $otp))) {
            $payload['attempts'] = (int) $payload['attempts'] + 1;
            Cache::put($this->key($challenge), $payload, now()->addMinutes($this->expiresMinutes()));

            throw ValidationException::withMessages([
                'otp' => 'That code is not valid.',
            ]);
        }

        $user = User::query()->find($payload['user_id']);
        Cache::forget($this->key($challenge));

        if ($user === null) {
            throw $this->expired();
        }

        return [
            'user' => $user,
            'remember' => (bool) $payload['remember'],
        ];
    }

    /**
     * @return array{user_id: int, email: string, remember: bool, hash: string, attempts: int}
     */
    private function payload(string $challenge): array
    {
        $payload = Cache::get($this->key($challenge));

        if (! is_array($payload) || ! isset($payload['user_id'], $payload['hash'])) {
            throw $this->expired();
        }

        return $payload;
    }

    private function key(string $challenge): string
    {
        return 'auth_otp:'.$challenge;
    }

    private function skipKey(User $user): string
    {
        return 'auth_otp_skip:'.$user->id;
    }

    private function expiresMinutes(): int
    {
        return max(1, (int) config('auth.otp.expire', 10));
    }

    private function skipAfterLogoutMinutes(): int
    {
        return max(0, (int) config('auth.otp.skip_after_logout_minutes', 120));
    }

    private function expired(): ValidationException
    {
        return ValidationException::withMessages([
            'otp' => 'This code has expired. Sign in again to get a new one.',
        ]);
    }
}
