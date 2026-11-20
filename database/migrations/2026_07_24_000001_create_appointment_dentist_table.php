<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_dentist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dentist_id')->constrained()->cascadeOnDelete();
            $table->string('participation_role', 20)->default('collaborator');
            $table->timestamps();

            $table->unique(['appointment_id', 'dentist_id']);
            $table->index(['dentist_id', 'appointment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_dentist');
    }
};
