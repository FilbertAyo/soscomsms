<?php

namespace Database\Seeders;

use App\Enums\GatewayDriver;
use App\Models\Provider;
use Illuminate\Database\Seeder;

class ProviderSeeder extends Seeder
{
    public function run(): void
    {
        $driver = GatewayDriver::from((string) config('sms.gateway', GatewayDriver::Fake->value));

        Provider::query()->updateOrCreate(
            ['driver' => $driver],
            [
                'name' => match ($driver) {
                    GatewayDriver::Fake => 'Fake gateway',
                    GatewayDriver::Mobishastra => 'Mobishastra',
                },
                'profile_id' => config('sms.provider.profile_id') ?: null,
                'password' => config('sms.provider.password') ?: null,
                'base_url' => config('sms.provider.base_url') ?: null,
                'static_ip' => config('sms.provider.static_ip') ?: null,
                'country_code' => config('sms.provider.country_code') ?: 'ALL',
                'is_active' => true,
                'low_balance_threshold' => (int) config('sms.provider.low_balance_threshold', 10000),
                'fake_balance' => (int) config('sms.fake.balance', 100000),
            ],
        );
    }
}
