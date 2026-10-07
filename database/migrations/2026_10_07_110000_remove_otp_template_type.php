<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('message_templates')
            ->where('type', 'otp')
            ->update(['type' => 'transactional']);
    }

    public function down(): void
    {
        //
    }
};
