<?php

namespace Database\Seeders;

use App\Models\Dentist;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Administrador DentiFlow',
                'role' => 'superadmin',
                'password' => Hash::make(Str::random(64)),
            ]
        );

        User::firstOrCreate(
            ['email' => 'administrador@dentiflow.com'],
            [
                'name' => 'Administrador Operativo',
                'role' => 'admin',
                'password' => Hash::make(Str::random(64)),
                'telefono' => '2220000002',
                'puesto' => 'Administracion clinica',
            ]
        );

        User::firstOrCreate(
            ['email' => 'asistente@dentiflow.com'],
            [
                'name' => 'Asistente DentiFlow',
                'role' => 'asistente',
                'password' => Hash::make(Str::random(64)),
                'telefono' => '2220000000',
                'puesto' => 'Recepcion',
            ]
        );

        $doctor = User::firstOrCreate(
            ['email' => 'doctor@dentiflow.com'],
            [
                'name' => 'Dra. DentiFlow',
                'role' => 'doctor',
                'password' => Hash::make(Str::random(64)),
                'telefono' => '2220000001',
                'puesto' => 'Odontologia general',
            ]
        );

        Dentist::firstOrCreate(
            ['user_id' => $doctor->id],
            [
                'nombre' => $doctor->name,
                'telefono' => $doctor->telefono,
                'especialidad' => $doctor->puesto,
            ]
        );
    }
}
