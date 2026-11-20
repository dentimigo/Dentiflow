<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        DB::table('users')->insertOrIgnore(
            [
                'email' => 'admin@gmail.com',
                'name' => 'Administrador DentiFlow',
                'password' => Hash::make(Str::random(64)),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        if (app()->environment('local', 'testing')) {
            DB::table('users')->where('email', 'admin@gmail.com')->delete();
        }
    }
};
