<?php

use App\Models\ClinicSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_settings', function (Blueprint $table) {
            $table->text('assistant_allowed_sections')->nullable()->after('administrative_audit_enabled');
        });

        DB::table('clinic_settings')->update([
            'assistant_allowed_sections' => json_encode(ClinicSetting::DEFAULTS['assistant_allowed_sections']),
        ]);
    }

    public function down(): void
    {
        Schema::table('clinic_settings', function (Blueprint $table) {
            $table->dropColumn('assistant_allowed_sections');
        });
    }
};
