<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('body');
            $table->string('encoding');
            $table->unsignedInteger('character_count');
            $table->unsignedInteger('segment_count');
            $table->timestamps();

            $table->unique(['customer_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};
