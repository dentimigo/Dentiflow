<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Dentist;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_assistant_can_restore_a_cancelled_appointment_when_the_schedule_is_free(): void
    {
        [$appointment] = $this->cancelledAppointment();

        $this->withSession(['access_role' => 'asistente'])
            ->get(route('citas.historial'))
            ->assertOk()
            ->assertSee('Historial de citas')
            ->assertSee('#'.$appointment->id)
            ->assertSee(route('citas.show', $appointment), false)
            ->assertDontSee('Acciones')
            ->assertDontSee('Restaurar');

        $this->post(route('citas.restaurar', $appointment))
            ->assertRedirect(route('citas.historial'));

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'estado' => 'Pendiente',
        ]);
    }

    public function test_doctor_only_sees_and_restores_own_cancelled_appointments(): void
    {
        [$ownAppointment, $doctor, $ownDentist] = $this->cancelledAppointment(true);
        [$otherAppointment] = $this->cancelledAppointment();

        $session = [
            'access_role' => 'doctor',
            'access_user_id' => $doctor->id,
            'access_email' => $doctor->email,
            'access_name' => $doctor->name,
        ];

        $this->withSession($session)
            ->get(route('citas.historial'))
            ->assertOk()
            ->assertSee('#'.$ownAppointment->id)
            ->assertDontSee('#'.$otherAppointment->id);

        $this->withSession($session)
            ->post(route('citas.restaurar', $otherAppointment))
            ->assertForbidden();

        $this->assertSame($ownDentist->id, $ownAppointment->dentist_id);
    }

    public function test_restore_redirects_to_reprogramming_when_the_old_schedule_is_occupied(): void
    {
        [$cancelled, , $dentist, $patient] = $this->cancelledAppointment();
        Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'date' => $cancelled->date,
            'time' => $cancelled->time,
            'end_time' => $cancelled->end_time,
            'tipo' => 'Cita en conflicto',
            'estado' => 'Confirmada',
        ]);

        $this->withSession(['access_role' => 'asistente'])
            ->post(route('citas.restaurar', $cancelled))
            ->assertRedirect(route('citas.edit', $cancelled))
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('appointments', [
            'id' => $cancelled->id,
            'estado' => 'Cancelada',
        ]);
    }

    private function cancelledAppointment(bool $linkedDoctor = false): array
    {
        $doctor = $linkedDoctor ? User::factory()->create(['role' => 'doctor']) : null;
        $dentist = Dentist::create([
            'user_id' => $doctor?->id,
            'nombre' => $linkedDoctor ? 'Dra. Historial Propio' : 'Dra. Historial '.uniqid(),
            'telefono' => '2220000000',
        ]);
        $patient = Patient::create([
            'nombre' => 'Paciente '.uniqid(),
            'telefono' => '2220000001',
            'estado' => 'Activo',
        ]);
        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'date' => now()->addDays(3)->toDateString(),
            'time' => '10:00:00',
            'end_time' => '10:30:00',
            'tipo' => 'Consulta',
            'estado' => 'Cancelada',
        ]);

        return [$appointment, $doctor, $dentist, $patient];
    }
}
