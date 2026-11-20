<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->boolean('reminder_sent')->default(false)->after('cancellation_reason');
        });

        Schema::create('calendar_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dentist_id')->constrained('dentists')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('type')->default('Bloqueo');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['dentist_id', 'date', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_blocks');

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('reminder_sent');
        });
    }
};
