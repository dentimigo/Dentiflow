<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_workspaces', function (Blueprint $table) {
            $table->boolean('stripe_cancellation_pending')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('clinic_workspaces', function (Blueprint $table) {
            $table->dropColumn('stripe_cancellation_pending');
        });
    }
};
