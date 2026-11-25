<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->boolean('personalized')->default(false);
        });

        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->string('recipient_name', 120)->nullable();
            $table->text('body')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->dropColumn(['recipient_name', 'body']);
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('personalized');
        });
    }
};
