<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pricing_tier_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('unit_price_tzs');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'pricing_tier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_prices');
    }
};
