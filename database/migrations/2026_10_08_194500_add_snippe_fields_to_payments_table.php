<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('lipa_namba')->nullable()->change();
            $table->string('snippe_reference')->nullable()->unique();
            $table->string('external_reference')->nullable();
            $table->string('provider')->nullable();
            $table->unsignedInteger('fee_tzs')->nullable();
            $table->unsignedInteger('net_tzs')->nullable();
            $table->string('webhook_event_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['snippe_reference']);
            $table->dropUnique(['webhook_event_id']);
            $table->dropColumn([
                'snippe_reference',
                'external_reference',
                'provider',
                'fee_tzs',
                'net_tzs',
                'webhook_event_id',
            ]);
            $table->string('lipa_namba')->nullable(false)->change();
        });
    }
};
