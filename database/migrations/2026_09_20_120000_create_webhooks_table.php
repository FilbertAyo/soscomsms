<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url', 500);
            $table->text('secret');
            $table->json('events');
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['customer_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};
