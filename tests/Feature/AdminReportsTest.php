<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Dentist;
use App\Models\Patient;
use App\Models\Treatment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_reports_management_section(): void
    {
        $appointment = $this->finishedAppointmentWithReport();

        $this->withSession(['access_role' => 'superadmin'])
            ->get(route('admin.reports'))
            ->assertOk()
            ->assertSee('Configuracion de reportes')
            ->assertSee($appointment->patient->nombre)
            ->assertSee('Editar cita')
            ->assertSee('Eliminar reporte');
    }

    public function test_admin_can_update_finished_appointment_and_sync_report(): void
    {
        $appointment = $this->finishedAppointmentWithReport();
        $newPatient = Patient::create(['nombre' => 'Paciente corregido', 'telefono' => '2220000099', 'estado' => 'Activo']);
        $newDentist = Dentist::create(['nombre' => 'Dra. Corregida', 'telefono' => '2220000098']);
        Treatment::create([
            'paciente' => '',
            'tratamiento' => 'Consulta corregida',
            'tipo' => 'General',
            'fecha' => now()->toDateString(),
            'estado' => 'Activo',
            'costo' => 850,
        ]);

        $this->withSession(['access_role' => 'superadmin'])
            ->put(route('admin.reports.appointments.update', $appointment), [
                'patient_id' => $newPatient->id,
                'dentist_id' => $newDentist->id,
                'date' => '2026-07-01',
                'time' => '14:10',
                'end_time' => '14:45',
                'tipo' => 'Consulta corregida',
            ])
            ->assertRedirect(route('admin.reports'));

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'patient_id' => $newPatient->id,
            'dentist_id' => $newDentist->id,
            'date' => '2026-07-01 00:00:00',
            'time' => '14:10:00',
            'end_time' => '14:45:00',
            'tipo' => 'Consulta corregida',
            'estado' => 'Terminada',
        ]);
        $this->assertDatabaseHas('treatments', [
            'appointment_id' => $appointment->id,
            'patient_id' => $newPatient->id,
            'tratamiento' => 'Consulta corregida',
            'tipo' => 'General',
            'fecha' => '2026-07-01',
            'estado' => 'Realizado',
            'costo' => 850,
        ]);
    }

    public function test_admin_can_delete_report_without_deleting_finished_appointment(): void
    {
        $appointment = $this->finishedAppointmentWithReport();
        $report = Treatment::where('appointment_id', $appointment->id)->firstOrFail();

        $this->withSession(['access_role' => 'superadmin'])
            ->delete(route('admin.reports.destroy', $report))
            ->assertRedirect(route('admin.reports'));

        $this->assertDatabaseMissing('treatments', ['id' => $report->id]);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'estado' => 'Terminada',
        ]);
    }

    private function finishedAppointmentWithReport(): Appointment
    {
        $patient = Patient::create(['nombre' => 'Paciente reporte admin', 'telefono' => '2220000000', 'estado' => 'Activo']);
        $dentist = Dentist::create(['nombre' => 'Dra. Reportes', 'telefono' => '2220000001']);
        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'date' => '2026-06-30',
            'time' => '11:00:00',
            'end_time' => '11:45:00',
            'tipo' => 'Limpieza admin',
            'estado' => 'Terminada',
        ]);
        Treatment::create([
            'paciente' => '',
            'tratamiento' => 'Limpieza admin',
            'tipo' => 'Preventivo',
            'fecha' => now()->toDateString(),
            'estado' => 'Activo',
            'costo' => 500,
        ]);
        Treatment::create([
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'paciente' => $patient->nombre,
            'tratamiento' => 'Limpieza admin',
            'tipo' => 'Preventivo',
            'fecha' => '2026-06-30',
            'estado' => 'Realizado',
            'costo' => 500,
        ]);

        return $appointment->load(['patient', 'dentist']);
    }
}
