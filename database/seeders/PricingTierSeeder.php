<?php

namespace Database\Seeders;

use App\Models\PricingTier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class PricingTierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tiers = [
            ['min_quantity' => 100, 'max_quantity' => 999, 'unit_price_tzs' => 25, 'sort_order' => 1],
            ['min_quantity' => 1000, 'max_quantity' => 4999, 'unit_price_tzs' => 23, 'sort_order' => 2],
            ['min_quantity' => 5000, 'max_quantity' => null, 'unit_price_tzs' => 20, 'sort_order' => 3],
        ];

        PricingTier::query()
            ->whereNotIn('min_quantity', array_column($tiers, 'min_quantity'))
            ->delete();

        foreach ($tiers as $tier) {
            PricingTier::query()->updateOrCreate(
                ['min_quantity' => $tier['min_quantity']],
                $tier
            );
        }

        Cache::forget('pricing-tiers');
    }
}
