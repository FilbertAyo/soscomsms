<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sender_ids', function (Blueprint $table) {
            $table->boolean('available_to_all')->default(false);
        });

        DB::table('sender_ids')
            ->whereNull('customer_id')
            ->update(['available_to_all' => true]);

        Schema::create('sender_id_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['sender_id_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sender_id_grants');

        Schema::table('sender_ids', function (Blueprint $table) {
            $table->dropColumn('available_to_all');
        });
    }
};
