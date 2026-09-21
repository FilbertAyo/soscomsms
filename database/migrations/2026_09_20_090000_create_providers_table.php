<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('driver');
            $table->string('profile_id')->nullable();
            $table->text('password')->nullable();
            $table->string('base_url')->nullable();
            $table->string('static_ip')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('low_balance_threshold')->default(10000);
            $table->integer('last_balance')->nullable();
            $table->timestamp('last_balance_at')->nullable();
            $table->unsignedInteger('fake_balance')->nullable();
            $table->timestamps();

            $table->unique('driver');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
