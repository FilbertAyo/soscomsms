<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->string('country_code', 16)->nullable()->after('static_ip');
            $table->timestamp('static_ip_whitelisted_at')->nullable()->after('country_code');
            $table->timestamp('credentials_confirmed_at')->nullable()->after('static_ip_whitelisted_at');
            $table->timestamp('low_balance_alerted_at')->nullable()->after('last_balance_at');
        });
    }

    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->dropColumn([
                'country_code',
                'static_ip_whitelisted_at',
                'credentials_confirmed_at',
                'low_balance_alerted_at',
            ]);
        });
    }
};
