<?php

namespace Database\Factories;

use App\Models\ApiKey;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiKey>
 */
class ApiKeyFactory extends Factory
{
    protected $model = ApiKey::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plain = 'sk_live_'.bin2hex(random_bytes(24));

        return [
            'customer_id' => Customer::factory()->complete(),
            'name' => fake()->words(2, true).' key',
            'key_prefix' => substr($plain, 0, 15),
            'key_hash' => hash('sha256', $plain),
            'last_used_at' => null,
            'revoked_at' => null,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'revoked_at' => now(),
        ]);
    }
}
