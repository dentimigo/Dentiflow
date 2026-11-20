<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\ClinicSetting;
use App\Models\Dentist;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_only_shows_system_management_information(): void
    {
        $patient = Patient::create(['nombre' => 'Paciente Admin', 'telefono' => '2220000000', 'estado' => 'Activo']);
        $dentist = Dentist::create(['nombre' => 'Dra. Indicadores', 'telefono' => '2220000000']);

        Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'date' => now()->toDateString(),
            'time' => '10:00:00',
            'tipo' => 'Limpieza',
            'estado' => 'Pendiente',
        ]);

        Treatment::create([
            'patient_id' => $patient->id,
            'paciente' => $patient->nombre,
            'tratamiento' => 'Limpieza',
            'tipo' => 'Preventivo',
            'fecha' => now()->toDateString(),
            'estado' => 'Realizado',
            'costo' => 500,
        ]);

        $this->withSession(['access_role' => 'superadmin'])
            ->get(route('inicio', ['dentista' => $dentist->id]))
            ->assertOk()
            ->assertSee('Control del sistema')
            ->assertSee('Cuentas registradas')
            ->assertSee('Servicios del sistema')
            ->assertSee('Auditoria reciente')
            ->assertSee('Acciones administrativas')
            ->assertSee('Control y seguridad')
            ->assertDontSee('Gestionar usuarios')
            ->assertDontSee('Citas de hoy')
            ->assertDontSee('Desempeno del consultorio')
            ->assertDontSee('Paciente Admin')
            ->assertDontSee('Dra. Indicadores');
    }

    public function test_admin_calendar_has_its_own_section_and_filters_by_dentist(): void
    {
        $patient = Patient::create(['nombre' => 'Paciente Calendario', 'telefono' => '2220000000', 'estado' => 'Activo']);
        $dentist = Dentist::create(['nombre' => 'Dra. Calendario', 'telefono' => '2220000000']);

        Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'date' => now()->toDateString(),
            'time' => '12:00:00',
            'tipo' => 'Revision',
            'estado' => 'Confirmada',
        ]);

        $this->withSession(['access_role' => 'superadmin'])
            ->get(route('admin.calendar', ['dentista' => $dentist->id]))
            ->assertOk()
            ->assertViewIs('admin.calendar')
            ->assertSee('Calendario del consultorio')
            ->assertSee('Calendario por doctor')
            ->assertSee('Dra. Calendario')
            ->assertSee('Paciente Calendario');
    }

    public function test_admin_dashboard_uses_clinic_settings_for_system_status(): void
    {
        ClinicSetting::create([
            'clinic_name' => 'Clinica DentiFlow',
            'support_email' => 'soporte@dentiflow.com',
            'appointment_duration' => 60,
            'schedule_interval' => 30,
            'opening_time' => '09:00',
            'closing_time' => '18:00',
            'default_appointment_status' => 'Pendiente',
            'maintenance_mode_enabled' => true,
        ]);

        $this->withSession(['access_role' => 'superadmin'])
            ->get(route('inicio'))
            ->assertOk()
            ->assertViewHas('system.status', 'Modo mantenimiento activo');
    }

    public function test_doctor_dashboard_only_shows_own_agenda(): void
    {
        $doctor = User::create([
            'name' => 'Dra. Propia',
            'email' => 'propia@dentiflow.test',
            'role' => 'doctor',
            'password' => 'password',
        ]);
        $ownDentist = Dentist::create(['user_id' => $doctor->id, 'nombre' => 'Dra. Propia', 'telefono' => '2220000000']);
        $otherDentist = Dentist::create(['nombre' => 'Dr. Externo', 'telefono' => '2220000001']);
        $ownPatient = Patient::create(['nombre' => 'Paciente Propio', 'telefono' => '2220000002', 'estado' => 'Activo']);
        $otherPatient = Patient::create(['nombre' => 'Paciente Externo', 'telefono' => '2220000003', 'estado' => 'Activo']);

        Appointment::create([
            'patient_id' => $ownPatient->id,
            'dentist_id' => $ownDentist->id,
            'date' => now()->toDateString(),
            'time' => '10:00:00',
            'tipo' => 'Limpieza',
            'estado' => 'Confirmada',
        ]);
        Appointment::create([
            'patient_id' => $otherPatient->id,
            'dentist_id' => $otherDentist->id,
            'date' => now()->toDateString(),
            'time' => '11:00:00',
            'tipo' => 'Consulta',
            'estado' => 'Confirmada',
        ]);

        $this->withSession([
                'access_role' => 'doctor',
                'access_user_id' => $doctor->id,
                'access_email' => $doctor->email,
                'access_name' => $doctor->name,
            ])
            ->get(route('inicio'))
            ->assertOk()
            ->assertSee('Paciente Propio')
            ->assertDontSee('Paciente Externo');
    }

    public function test_doctor_dashboard_shows_monthly_costs_from_own_realized_treatments(): void
    {
        $doctor = User::create([
            'name' => 'Dra. Costos',
            'email' => 'costos@dentiflow.test',
            'role' => 'doctor',
            'password' => 'password',
        ]);
        $ownDentist = Dentist::create(['user_id' => $doctor->id, 'nombre' => 'Dra. Costos', 'telefono' => '2220000000']);
        $otherDentist = Dentist::create(['nombre' => 'Dr. Otros Costos', 'telefono' => '2220000001']);
        $patient = Patient::create(['nombre' => 'Paciente Costo', 'telefono' => '2220000002', 'estado' => 'Activo']);
        $otherPatient = Patient::create(['nombre' => 'Paciente Otro Costo', 'telefono' => '2220000003', 'estado' => 'Activo']);

        $ownAppointment = Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $ownDentist->id,
            'date' => now()->toDateString(),
            'time' => '10:00:00',
            'tipo' => 'Endodoncia',
            'estado' => 'Asistida',
        ]);
        $otherAppointment = Appointment::create([
            'patient_id' => $otherPatient->id,
            'dentist_id' => $otherDentist->id,
            'date' => now()->toDateString(),
            'time' => '11:00:00',
            'tipo' => 'Implante',
            'estado' => 'Asistida',
        ]);

        Treatment::create([
            'patient_id' => $patient->id,
            'appointment_id' => $ownAppointment->id,
            'paciente' => $patient->nombre,
            'tratamiento' => 'Endodoncia',
            'tipo' => 'Correctivo',
            'fecha' => now()->toDateString(),
            'estado' => 'Realizado',
            'costo' => 1200,
        ]);
        Treatment::create([
            'patient_id' => $otherPatient->id,
            'appointment_id' => $otherAppointment->id,
            'paciente' => $otherPatient->nombre,
            'tratamiento' => 'Implante',
            'tipo' => 'Quirurgico',
            'fecha' => now()->toDateString(),
            'estado' => 'Realizado',
            'costo' => 4000,
        ]);

        $this->withSession([
                'access_role' => 'doctor',
                'access_user_id' => $doctor->id,
                'access_email' => $doctor->email,
                'access_name' => $doctor->name,
            ])
            ->get(route('inicio'))
            ->assertOk()
            ->assertSee('Tratamientos realizados')
            ->assertSee('Completados este mes')
            ->assertDontSee('Costos generados este mes')
            ->assertDontSee('$1.200')
            ->assertDontSee('$4.000')
            ->assertDontSee('Implante');
    }
}
