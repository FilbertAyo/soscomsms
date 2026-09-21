<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->integer('quantity');
            $table->unsignedInteger('customer_unit_price')->nullable();
            $table->unsignedInteger('provider_unit_cost')->nullable();
            $table->unsignedInteger('amount_tzs')->nullable();
            $table->unsignedInteger('balance_after');
            $table->string('reference')->unique();
            $table->string('description');
            $table->foreignId('related_transaction_id')
                ->nullable()
                ->constrained('credit_transactions')
                ->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
