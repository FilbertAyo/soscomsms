<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_tiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('min_quantity');
            $table->unsignedInteger('max_quantity')->nullable();
            $table->unsignedInteger('unit_price_tzs');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique('min_quantity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_tiers');
    }
};
