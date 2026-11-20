<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_workspaces', function (Blueprint $table) {
            $table->boolean('trial_required')->default(false);
            $table->string('trial_plan', 20)->nullable();
            $table->dateTime('trial_ends_at')->nullable();
        });

        DB::table('clinic_workspaces')->whereNotNull('owner_user_id')->update(['trial_required' => true]);
    }

    public function down(): void
    {
        Schema::table('clinic_workspaces', function (Blueprint $table) {
            $table->dropColumn(['trial_required', 'trial_plan', 'trial_ends_at']);
        });
    }
};
