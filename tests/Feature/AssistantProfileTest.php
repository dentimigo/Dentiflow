<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Dentist;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_assistant_profile_uses_assistant_interface(): void
    {
        $assistant = User::factory()->create([
            'name' => 'Asistente Recepcion',
            'email' => 'asistente@example.test',
            'telefono' => '222 111 0000',
            'puesto' => 'Recepcion y gestion de citas',
        ]);
        $patient = Patient::create([
            'nombre' => 'Paciente Próximo',
            'telefono' => '2220000100',
            'estado' => 'Activo',
        ]);
        $dentist = Dentist::create([
            'nombre' => 'Dra. Agenda Visible',
            'telefono' => '2220000101',
        ]);
        Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'date' => now()->addDay()->toDateString(),
            'time' => '10:00:00',
            'tipo' => 'Consulta',
            'estado' => 'Confirmada',
        ]);

        $this->withSession([
            'access_role' => 'asistente',
            'access_user_id' => $assistant->id,
            'access_email' => $assistant->email,
            'access_name' => $assistant->name,
        ])->get(route('perfil'))
            ->assertOk()
            ->assertSee('Perfil de asistente')
            ->assertSee('Recepcion y operacion')
            ->assertSee('Área operativa')
            ->assertSee('Panel operativo')
            ->assertSee('Próxima cita del consultorio')
            ->assertSee('Doctor: Dra. Agenda Visible')
            ->assertSee('Recepcion y gestion de citas')
            ->assertDontSee('Odontologia general');
    }

    public function test_assistant_can_update_own_profile(): void
    {
        $assistant = User::factory()->create([
            'name' => 'Asistente Viejo',
            'email' => 'viejo@example.test',
            'telefono' => 'No registrado',
            'puesto' => 'Recepcion',
        ]);

        $this->withSession([
            'access_role' => 'asistente',
            'access_user_id' => $assistant->id,
            'access_email' => $assistant->email,
            'access_name' => $assistant->name,
        ])->post(route('perfil.update'), [
            'nombre' => 'Asistente Nuevo',
            'email' => 'nuevo@example.test',
            'telefono' => '222 333 4444',
            'especialidad' => 'Recepcion principal',
        ])->assertRedirect(route('perfil'));

        $this->assertDatabaseHas('users', [
            'id' => $assistant->id,
            'name' => 'Asistente Nuevo',
            'email' => 'nuevo@example.test',
            'telefono' => '222 333 4444',
            'puesto' => 'Recepcion principal',
        ]);
    }
}
