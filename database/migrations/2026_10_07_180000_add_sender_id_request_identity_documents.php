<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sender_id_requests', function (Blueprint $table) {
            $table->string('sample_content', 160)->nullable()->after('description');
            $table->string('identity_document_type', 40)->nullable()->after('sample_content');
        });

        Schema::create('sender_id_request_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->unsignedInteger('size');
            $table->binary('contents');
            $table->timestamps();
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE sender_id_request_documents MODIFY contents MEDIUMBLOB NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sender_id_request_documents');

        Schema::table('sender_id_requests', function (Blueprint $table) {
            $table->dropColumn(['sample_content', 'identity_document_type']);
        });
    }
};
