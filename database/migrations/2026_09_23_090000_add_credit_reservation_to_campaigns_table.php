<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->unsignedInteger('reserved_segments')->default(0)->after('total_segments');
            $table->unsignedInteger('consumed_segments')->default(0)->after('reserved_segments');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['reserved_segments', 'consumed_segments']);
        });
    }
};
