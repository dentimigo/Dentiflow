<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_workspaces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('clinic_workspace_members', function (Blueprint $table) {
            $table->foreignId('clinic_workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->timestamps();
            $table->primary(['clinic_workspace_id', 'user_id']);
        });

        DB::transaction(function () {
            $now = now();
            $workspaceId = DB::table('clinic_workspaces')->insertGetId([
                'name' => DB::table('clinic_settings')->orderBy('id')->value('clinic_name') ?: 'Clínica DentiFlow',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('users')
                ->whereIn('role', ['admin', 'doctor', 'asistente'])
                ->select(['id', 'role'])
                ->chunkById(500, function ($users) use ($workspaceId, $now) {
                    DB::table('clinic_workspace_members')->insert($users->map(fn ($user) => [
                        'clinic_workspace_id' => $workspaceId,
                        'user_id' => $user->id,
                        'role' => $user->role,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all());
                });
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_workspace_members');
        Schema::dropIfExists('clinic_workspaces');
    }
};
