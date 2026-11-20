<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_blocks', function (Blueprint $table) {
            $table->foreignId('dentist_id')->nullable()->change();
            $table->foreignId('target_user_id')->nullable()->after('dentist_id')->constrained('users')->nullOnDelete();
            $table->index(['target_user_id', 'date'], 'calendar_blocks_target_user_date_index');
        });
    }

    public function down(): void
    {
        DB::table('calendar_blocks')->whereNull('dentist_id')->delete();

        Schema::table('calendar_blocks', function (Blueprint $table) {
            $table->dropIndex('calendar_blocks_target_user_date_index');
            $table->dropConstrainedForeignId('target_user_id');
            $table->foreignId('dentist_id')->nullable(false)->change();
        });
    }
};
