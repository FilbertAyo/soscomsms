<?php

use Database\Seeders\PricingTierSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        (new PricingTierSeeder)->run();
    }

    public function down(): void
    {
        DB::table('pricing_tiers')->delete();

        $now = now();
        DB::table('pricing_tiers')->insert([
            ['min_quantity' => 500, 'max_quantity' => 999, 'unit_price_tzs' => 30, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['min_quantity' => 1000, 'max_quantity' => 2499, 'unit_price_tzs' => 28, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['min_quantity' => 2500, 'max_quantity' => 9999, 'unit_price_tzs' => 25, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['min_quantity' => 10000, 'max_quantity' => 49999, 'unit_price_tzs' => 22, 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['min_quantity' => 50000, 'max_quantity' => null, 'unit_price_tzs' => 20, 'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
};
