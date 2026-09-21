<?php

namespace Database\Seeders;

use App\Models\PricingTier;
use Illuminate\Database\Seeder;

class PricingTierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tiers = [
            ['min_quantity' => 500, 'max_quantity' => 999, 'unit_price_tzs' => 30, 'sort_order' => 1],
            ['min_quantity' => 1000, 'max_quantity' => 2499, 'unit_price_tzs' => 28, 'sort_order' => 2],
            ['min_quantity' => 2500, 'max_quantity' => 9999, 'unit_price_tzs' => 25, 'sort_order' => 3],
            ['min_quantity' => 10000, 'max_quantity' => 49999, 'unit_price_tzs' => 22, 'sort_order' => 4],
            ['min_quantity' => 50000, 'max_quantity' => null, 'unit_price_tzs' => 20, 'sort_order' => 5],
        ];

        foreach ($tiers as $tier) {
            PricingTier::query()->updateOrCreate(
                ['min_quantity' => $tier['min_quantity']],
                $tier
            );
        }
    }
}
