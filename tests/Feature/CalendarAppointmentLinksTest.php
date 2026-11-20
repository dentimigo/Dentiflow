<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Dentist;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarAppointmentLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_month_calendar_appointment_opens_detail_view(): void
    {
        $patient = Patient::create([
            'nombre' => 'Paciente Calendario',
            'telefono' => '2220000000',
            'estado' => 'Activo',
        ]);
        $dentist = Dentist::create([
            'nombre' => 'Dra. Calendario',
            'telefono' => '2220000001',
        ]);
        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'date' => now()->startOfMonth()->toDateString(),
            'time' => '13:00:00',
            'tipo' => 'Consulta',
            'estado' => 'Pendiente',
        ]);

        $this->withSession(['access_role' => 'asistente'])
            ->get(route('calendario', [
                'view' => 'mes',
                'year' => now()->year,
                'month' => now()->month,
            ]))
            ->assertOk()
            ->assertSee(route('citas.show', ['id' => $appointment->id]), false)
            ->assertSee('Ver detalle de cita de Paciente Calendario');
    }

    public function test_day_and_week_navigation_use_the_requested_date(): void
    {
        $this->withSession(['access_role' => 'doctor'])
            ->get(route('calendario', ['view' => 'dia', 'date' => '2026-07-10']))
            ->assertOk()
            ->assertSee('viernes 10 de julio de 2026')
            ->assertSee(route('calendario', ['view' => 'dia', 'date' => '2026-07-09']))
            ->assertSee(route('calendario', ['view' => 'dia', 'date' => '2026-07-11']));

        $this->withSession(['access_role' => 'doctor'])
            ->get(route('calendario', ['view' => 'semana', 'date' => '2026-07-15']))
            ->assertOk()
            ->assertSee('13 jul.')
            ->assertSee('19 jul. 2026')
            ->assertSee(route('calendario', ['view' => 'semana', 'date' => '2026-07-08']))
            ->assertSee(route('calendario', ['view' => 'semana', 'date' => '2026-07-22']));
    }

    public function test_assistant_can_see_the_dentist_name_on_week_and_month_calendars(): void
    {
        $patient = Patient::create([
            'nombre' => 'Paciente Asistente',
            'telefono' => '2220000010',
            'estado' => 'Activo',
        ]);
        $dentist = Dentist::create([
            'nombre' => 'Dra. Nombre Visible',
            'telefono' => '2220000011',
        ]);
        Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'date' => '2026-07-15',
            'time' => '10:00:00',
            'tipo' => 'Consulta',
            'estado' => 'Pendiente',
        ]);

        $this->withSession(['access_role' => 'asistente'])
            ->get(route('calendario', ['view' => 'semana', 'date' => '2026-07-15']))
            ->assertOk()
            ->assertSee('Doctor: Dra. Nombre Visible');

        $this->withSession(['access_role' => 'asistente'])
            ->get(route('calendario', ['view' => 'mes', 'year' => 2026, 'month' => 7]))
            ->assertOk()
            ->assertSee('Dr: Dra. Nombre Visible');
    }
}
