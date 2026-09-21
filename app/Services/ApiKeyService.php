<?php

namespace App\Services;

use App\Models\ApiKey;
use App\Models\Customer;
use Illuminate\Validation\ValidationException;

class ApiKeyService
{
    /**
     * @return array{key: ApiKey, plaintext: string}
     */
    public function create(Customer $customer, string $name): array
    {
        $max = (int) config('sms.api.max_keys', 10);
        $active = $customer->apiKeys()->whereNull('revoked_at')->count();

        if ($active >= $max) {
            throw ValidationException::withMessages([
                'name' => "You can have at most {$max} active API keys. Revoke one before creating another.",
            ]);
        }

        $plaintext = $this->generatePlaintext();

        $key = $customer->apiKeys()->create([
            'name' => $name,
            'key_prefix' => substr($plaintext, 0, 15),
            'key_hash' => self::hash($plaintext),
        ]);

        return [
            'key' => $key,
            'plaintext' => $plaintext,
        ];
    }

    public function findByPlaintext(string $plaintext): ?ApiKey
    {
        $plaintext = trim($plaintext);

        if ($plaintext === '' || ! str_starts_with($plaintext, (string) config('sms.api.key_prefix', 'sk_live_'))) {
            return null;
        }

        return ApiKey::query()
            ->where('key_hash', self::hash($plaintext))
            ->first();
    }

    public function revoke(ApiKey $key): ApiKey
    {
        if ($key->revoked_at === null) {
            $key->forceFill(['revoked_at' => now()])->save();
        }

        return $key->fresh() ?? $key;
    }

    public function touchLastUsed(ApiKey $key): void
    {
        if ($key->last_used_at !== null && $key->last_used_at->gte(now()->subMinute())) {
            return;
        }

        $key->forceFill(['last_used_at' => now()])->save();
    }

    public static function hash(string $plaintext): string
    {
        return hash('sha256', $plaintext);
    }

    private function generatePlaintext(): string
    {
        $prefix = (string) config('sms.api.key_prefix', 'sk_live_');

        return $prefix.bin2hex(random_bytes(24));
    }
}
