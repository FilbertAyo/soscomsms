<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sender_id_requests', function (Blueprint $table) {
            $table->string('website_url')->nullable()->after('organization');
        });
    }

    public function down(): void
    {
        Schema::table('sender_id_requests', function (Blueprint $table) {
            $table->dropColumn('website_url');
        });
    }
};
