<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'telefono')) {
                $table->string('telefono')->nullable()->after('password');
            }

            if (! Schema::hasColumn('users', 'puesto')) {
                $table->string('puesto')->nullable()->after('telefono');
            }
        });

        if (! app()->environment('local', 'testing')) {
            return;
        }

        DB::table('users')->insertOrIgnore(
            [
                'email' => 'asistente@dentiflow.com',
                'name' => 'Asistente DentiFlow',
                'password' => Hash::make(Str::random(64)),
                'telefono' => 'No registrado',
                'puesto' => 'Recepcion y gestion de citas',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        if (app()->environment('local', 'testing')) {
            DB::table('users')->where('email', 'asistente@dentiflow.com')->delete();
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'puesto')) {
                $table->dropColumn('puesto');
            }

            if (Schema::hasColumn('users', 'telefono')) {
                $table->dropColumn('telefono');
            }
        });
    }
};
