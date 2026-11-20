<?php

namespace Tests\Feature;

use App\Models\Dentist;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_does_not_overwrite_existing_accounts_or_clinical_profiles(): void
    {
        $assistant = User::where('email', 'asistente@dentiflow.com')->firstOrFail();
        $assistant->update([
            'name' => 'Asistente personalizada',
            'password' => 'ClavePersonal123!',
            'telefono' => '2221234567',
        ]);

        $doctor = User::create([
            'name' => 'Doctor personalizado',
            'email' => 'doctor@dentiflow.com',
            'role' => 'doctor',
            'password' => 'ClaveDoctor123!',
            'telefono' => '2227654321',
            'puesto' => 'Endodoncia',
        ]);

        $profile = Dentist::create([
            'user_id' => $doctor->id,
            'nombre' => 'Perfil clínico personalizado',
            'telefono' => '2227654321',
            'especialidad' => 'Endodoncia',
        ]);

        $this->seed(DatabaseSeeder::class);

        $assistant->refresh();
        $doctor->refresh();
        $profile->refresh();

        $this->assertSame('Asistente personalizada', $assistant->name);
        $this->assertSame('2221234567', $assistant->telefono);
        $this->assertTrue(Hash::check('ClavePersonal123!', $assistant->password));
        $this->assertSame('Doctor personalizado', $doctor->name);
        $this->assertTrue(Hash::check('ClaveDoctor123!', $doctor->password));
        $this->assertSame('Perfil clínico personalizado', $profile->nombre);
        $this->assertSame('Endodoncia', $profile->especialidad);
    }
}
