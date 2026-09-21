<?php

namespace Database\Factories;

use App\Enums\GatewayDriver;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Provider>
 */
class ProviderFactory extends Factory
{
    protected $model = Provider::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Fake gateway',
            'driver' => GatewayDriver::Fake,
            'is_active' => true,
            'low_balance_threshold' => 10000,
            'fake_balance' => 100000,
        ];
    }

    public function mobishastra(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Mobishastra',
            'driver' => GatewayDriver::Mobishastra,
            'profile_id' => 'SOSCOM',
            'password' => 'secret',
            'base_url' => 'https://example.invalid',
            'static_ip' => '203.0.113.10',
            'country_code' => 'ALL',
            'static_ip_whitelisted_at' => now(),
            'credentials_confirmed_at' => now(),
            'fake_balance' => null,
        ]);
    }
}
