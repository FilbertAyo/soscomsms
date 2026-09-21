<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->index(['status', 'scheduled_at']);
            $table->index(['customer_id', 'created_at']);
        });

        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->index('type');
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->index(['status', 'id']);
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropIndex(['status', 'scheduled_at']);
            $table->dropIndex(['customer_id', 'created_at']);
        });

        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->dropIndex(['type']);
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropIndex(['status', 'id']);
            $table->dropIndex(['sent_at']);
        });
    }
};
