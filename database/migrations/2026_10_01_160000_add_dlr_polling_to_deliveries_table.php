<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->timestamp('dlr_checked_at')->nullable()->after('delivered_at');
            $table->unsignedInteger('dlr_attempts')->default(0)->after('dlr_checked_at');
            $table->index(['status', 'dlr_checked_at']);
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropIndex(['status', 'dlr_checked_at']);
            $table->dropColumn(['dlr_checked_at', 'dlr_attempts']);
        });
    }
};
