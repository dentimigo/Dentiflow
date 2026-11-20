<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'patients', 'dentists', 'appointments', 'treatments', 'medical_histories',
        'clinical_records', 'odontogram_entries', 'calendar_blocks', 'dentist_schedules',
        'inventory_items', 'clinic_settings', 'audit_logs',
    ];

    private const SCOPED_UNIQUES = [
        'patients' => ['email'],
        'dentists' => ['nombre', 'user_id'],
        'inventory_items' => ['material'],
    ];

    public function up(): void
    {
        $legacyId = DB::table('clinic_workspaces')->orderBy('id')->value('id');

        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) use ($legacyId) {
                $table->foreignId('clinic_workspace_id')->default($legacyId)->constrained()->restrictOnDelete();
                $table->index('clinic_workspace_id');
            });
        }

        foreach (self::SCOPED_UNIQUES as $name => $columns) {
            Schema::table($name, function (Blueprint $table) use ($columns) {
                foreach ($columns as $column) {
                    $table->unique([$column, 'clinic_workspace_id']);
                    $table->dropUnique([$column]);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::SCOPED_UNIQUES as $name => $columns) {
            Schema::table($name, function (Blueprint $table) use ($columns) {
                foreach ($columns as $column) {
                    $table->unique($column);
                    $table->dropUnique([$column, 'clinic_workspace_id']);
                }
            });
        }

        foreach (array_reverse(self::TABLES) as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropForeign(['clinic_workspace_id']);
                $table->dropIndex(['clinic_workspace_id']);
                $table->dropColumn('clinic_workspace_id');
            });
        }
    }
};
