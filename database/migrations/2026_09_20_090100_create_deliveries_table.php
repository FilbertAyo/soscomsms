<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_recipient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained()->restrictOnDelete();
            $table->string('provider_message_id')->nullable();
            $table->string('status');
            $table->string('provider_response_code', 16)->nullable();
            $table->text('provider_response')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique('campaign_recipient_id');
            $table->index('provider_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
