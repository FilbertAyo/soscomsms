<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedTinyInteger('profile_reminders_sent')->default(0)->after('sms_balance');
            $table->timestamp('profile_reminded_at')->nullable()->after('profile_reminders_sent');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['profile_reminders_sent', 'profile_reminded_at']);
        });
    }
};
