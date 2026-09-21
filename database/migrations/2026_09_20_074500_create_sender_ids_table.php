<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sender_ids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sender_id_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 13);
            $table->string('status')->default('active');
            $table->string('source')->default('request');
            $table->timestamps();

            $table->unique('name');
            $table->unique('sender_id_request_id');
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sender_ids');
    }
};
