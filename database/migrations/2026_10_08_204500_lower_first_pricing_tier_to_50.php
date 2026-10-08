<?php

use Database\Seeders\PricingTierSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('pricing_tiers')
            ->where('min_quantity', 100)
            ->update(['min_quantity' => 50, 'updated_at' => now()]);

        (new PricingTierSeeder)->run();
    }

    public function down(): void
    {
        DB::table('pricing_tiers')
            ->where('min_quantity', 50)
            ->update(['min_quantity' => 100, 'updated_at' => now()]);

        Cache::forget('pricing-tiers');
    }
};
