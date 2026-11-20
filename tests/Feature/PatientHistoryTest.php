<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Dentist;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_history_shows_completed_patient_appointment_time(): void
    {
        $user = User::factory()->create(['name' => 'Dra. Historial', 'role' => 'doctor']);
        $dentist = Dentist::create([
            'user_id' => $user->id,
            'nombre' => $user->name,
            'telefono' => '2220000000',
        ]);
        $otherDentist = Dentist::create([
            'nombre' => 'Dr. Otro Historial',
            'telefono' => '2220000001',
        ]);
        $patient = Patient::create(['nombre' => 'Paciente con hora', 'telefono' => '2220000002', 'estado' => 'Activo']);
        $otherPatient = Patient::create(['nombre' => 'Paciente ajeno historial', 'telefono' => '2220000003', 'estado' => 'Activo']);

        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'date' => now()->toDateString(),
            'time' => '13:15:00',
            'end_time' => '13:47:00',
            'tipo' => 'Limpieza historial',
            'estado' => 'Terminada',
        ]);
        $otherAppointment = Appointment::create([
            'patient_id' => $otherPatient->id,
            'dentist_id' => $otherDentist->id,
            'date' => now()->toDateString(),
            'time' => '15:00:00',
            'end_time' => '15:30:00',
            'tipo' => 'Consulta ajena',
            'estado' => 'Terminada',
        ]);

        $treatment = Treatment::create([
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'paciente' => $patient->nombre,
            'tratamiento' => 'Limpieza historial',
            'tipo' => 'Preventivo',
            'fecha' => now()->toDateString(),
            'estado' => 'Realizado',
            'costo' => 500,
        ]);
        Treatment::create([
            'patient_id' => $otherPatient->id,
            'appointment_id' => $otherAppointment->id,
            'paciente' => $otherPatient->nombre,
            'tratamiento' => 'Consulta ajena',
            'tipo' => 'Preventivo',
            'fecha' => now()->toDateString(),
            'estado' => 'Realizado',
            'costo' => 700,
        ]);

        $this->withSession([
            'access_role' => 'doctor',
            'access_user_id' => $user->id,
            'access_email' => $user->email,
        ])->get(route('pacientes.historial'))
            ->assertOk()
            ->assertSee('Paciente con hora')
            ->assertSee('Cita #'.$appointment->id)
            ->assertSee('01:15 PM - 01:47 PM')
            ->assertSee(route('citas.show', $appointment), false)
            ->assertSee(route('citas.edit', $appointment), false)
            ->assertSee(route('pacientes.historial.destroy', $treatment), false)
            ->assertDontSee('Paciente ajeno historial');
    }
}
