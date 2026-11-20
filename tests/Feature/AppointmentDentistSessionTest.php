<?php

namespace Tests\Feature;

use App\Models\Dentist;
use App\Models\Treatment;
use App\Models\User;
use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AppointmentDentistSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_calendar_only_shows_appointments_assigned_to_that_doctor(): void
    {
        $doctor = User::factory()->create(['name' => 'Dra. Agenda Privada', 'role' => 'doctor']);
        $ownDentist = Dentist::create(['user_id' => $doctor->id, 'nombre' => $doctor->name, 'telefono' => '2220000200']);
        $otherDentist = Dentist::create(['nombre' => 'Dr. Agenda Ajena', 'telefono' => '2220000201']);
        $ownPatient = Patient::create(['nombre' => 'Paciente Visible Doctor', 'telefono' => '2220000202', 'estado' => 'Activo']);
        $otherPatient = Patient::create(['nombre' => 'Paciente Oculto Doctor', 'telefono' => '2220000203', 'estado' => 'Activo']);
        $date = now()->addDay()->toDateString();

        Appointment::create(['patient_id' => $ownPatient->id, 'dentist_id' => $ownDentist->id, 'date' => $date, 'time' => '10:00:00', 'tipo' => 'Consulta propia', 'estado' => 'Pendiente']);
        Appointment::create(['patient_id' => $otherPatient->id, 'dentist_id' => $otherDentist->id, 'date' => $date, 'time' => '11:00:00', 'tipo' => 'Consulta ajena', 'estado' => 'Pendiente']);

        $session = [
            'access_role' => 'doctor',
            'access_user_id' => $doctor->id,
            'access_email' => $doctor->email,
            'access_name' => $doctor->name,
        ];

        $this->withSession($session)
            ->get(route('calendario', ['view' => 'dia', 'date' => $date]))
            ->assertOk()
            ->assertSee('Paciente Visible Doctor')
            ->assertDontSee('Paciente Oculto Doctor');

        $this->withSession(['access_role' => 'asistente'])
            ->get(route('calendario', ['view' => 'dia', 'date' => $date]))
            ->assertOk()
            ->assertSee('Paciente Visible Doctor')
            ->assertSee('Paciente Oculto Doctor');
    }

    public function test_appointment_uses_the_dentist_from_the_active_doctor_session(): void
    {
        $user = User::factory()->create(['name' => 'Dra. Sesión Activa']);
        $activeDentist = Dentist::create([
            'user_id' => $user->id,
            'nombre' => $user->name,
            'telefono' => '2220000000',
        ]);
        Treatment::create([
            'paciente' => 'Paciente base',
            'tratamiento' => 'Limpieza',
            'tipo' => 'Preventivo',
            'color' => '#16a34a',
            'fecha' => now()->toDateString(),
        ]);
        Patient::create(['nombre' => 'Paciente nuevo', 'telefono' => '2220000000', 'estado' => 'Activo']);

        $response = $this->withSession([
            'access_role' => 'doctor',
            'access_user_id' => $user->id,
            'access_name' => $user->name,
            'access_email' => $user->email,
        ])->post(route('citas.store'), [
            'paciente' => 'Paciente nuevo',
            'dentista' => 'Dentista manipulado',
            'fecha' => now()->next('Monday')->toDateString(),
            'hora' => '10:00',
            'tipo' => 'Limpieza',
        ]);

        $response->assertRedirect(route('citas'));
        $this->assertDatabaseHas('appointments', [
            'dentist_id' => $activeDentist->id,
            'tipo' => 'Limpieza',
            'color' => '#16a34a',
        ]);
        $this->assertDatabaseMissing('dentists', ['nombre' => 'Dentista manipulado']);
    }

    public function test_appointment_can_create_custom_emergency_treatment_from_schedule_form(): void
    {
        $patient = Patient::create(['nombre' => 'Paciente Emergencia', 'telefono' => '2220000300', 'estado' => 'Activo']);
        $dentist = Dentist::create(['nombre' => 'Dra. Urgencias', 'telefono' => '2220000301']);
        $date = now()->next('Monday')->toDateString();

        $response = $this->withSession(['access_role' => 'asistente'])->post(route('citas.store'), [
            'paciente' => $patient->nombre,
            'dentista' => $dentist->nombre,
            'fecha' => $date,
            'hora' => '10:00',
            'hora_fin' => '11:00',
            'tipo' => '__custom_treatment__',
            'tratamiento_emergencia' => 'Dolor agudo de emergencia',
        ]);

        $response->assertRedirect(route('citas'));
        $this->assertDatabaseHas('treatments', [
            'appointment_id' => null,
            'patient_id' => null,
            'tratamiento' => 'Dolor agudo de emergencia',
            'tipo' => 'Emergencia',
            'estado' => 'Activo',
            'costo' => 0,
        ]);
        $this->assertDatabaseHas('appointments', [
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'tipo' => 'Dolor agudo de emergencia',
            'time' => '10:00:00',
            'end_time' => '11:00:00',
        ]);
    }

    public function test_appointment_can_create_and_select_a_quick_patient_with_an_emergency_number(): void
    {
        $dentist = Dentist::create(['nombre' => 'Dra. Registro Rapido', 'telefono' => '2220000310']);
        Treatment::create([
            'paciente' => 'Catalogo',
            'tratamiento' => 'Consulta inicial',
            'tipo' => 'General',
            'fecha' => now()->toDateString(),
            'estado' => 'Activo',
        ]);

        $this->withSession(['access_role' => 'asistente'])
            ->get(route('citas.create'))
            ->assertOk()
            ->assertSee('+ Nuevo paciente rapido')
            ->assertSee('name="nuevo_paciente_nombre"', false)
            ->assertSee('Numero de emergencia');

        $this->withSession(['access_role' => 'asistente'])->post(route('citas.store'), [
            'paciente' => '__quick_patient__',
            'nuevo_paciente_nombre' => 'Paciente Sin Emergencia',
            'dentista' => $dentist->nombre,
            'fecha' => now()->next('Monday')->toDateString(),
            'hora' => '10:00',
            'hora_fin' => '11:00',
            'tipo' => 'Consulta inicial',
        ])->assertSessionHasErrors('nuevo_paciente_telefono');

        $this->assertDatabaseMissing('patients', ['nombre' => 'Paciente Sin Emergencia']);

        $response = $this->withSession(['access_role' => 'asistente'])->post(route('citas.store'), [
            'paciente' => '__quick_patient__',
            'nuevo_paciente_nombre' => 'Paciente Registro Rapido',
            'nuevo_paciente_telefono' => '2221234567',
            'dentista' => $dentist->nombre,
            'fecha' => now()->next('Monday')->toDateString(),
            'hora' => '12:00',
            'hora_fin' => '13:00',
            'tipo' => 'Consulta inicial',
        ]);

        $patient = Patient::where('nombre', 'Paciente Registro Rapido')->firstOrFail();

        $response->assertRedirect(route('citas'));
        $this->assertSame('2221234567', $patient->telefono);
        $this->assertDatabaseHas('appointments', [
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'tipo' => 'Consulta inicial',
        ]);
    }

    public function test_doctor_session_without_existing_user_creates_linked_dentist(): void
    {
        Treatment::create([
            'paciente' => 'Paciente base',
            'tratamiento' => 'Limpieza',
            'tipo' => 'Preventivo',
            'fecha' => now()->toDateString(),
        ]);
        Patient::create(['nombre' => 'Paciente nuevo', 'telefono' => '2220000000', 'estado' => 'Activo']);

        $response = $this->withSession([
            'access_role' => 'doctor',
            'access_user_id' => null,
            'access_name' => null,
            'access_email' => 'doctor.nuevo@example.test',
        ])->post(route('citas.store'), [
            'paciente' => 'Paciente nuevo',
            'dentista' => 'Dentista ignorado',
            'fecha' => now()->next('Monday')->toDateString(),
            'hora' => '10:00',
            'tipo' => 'Limpieza',
        ]);

        $response->assertRedirect(route('citas'));
        $this->assertDatabaseHas('users', ['email' => 'doctor.nuevo@example.test']);
        $this->assertDatabaseHas('dentists', ['nombre' => 'doctor.nuevo']);
        $this->assertDatabaseMissing('dentists', ['nombre' => 'Dentista ignorado']);
    }

    public function test_appointment_cannot_overlap_existing_dentist_block(): void
    {
        $patient = Patient::create(['nombre' => 'Paciente existente', 'telefono' => '', 'estado' => 'Activo']);
        Patient::create(['nombre' => 'Paciente nuevo', 'telefono' => '2220000000', 'estado' => 'Activo']);
        $dentist = Dentist::create(['nombre' => 'Dra. Agenda', 'telefono' => '2220000000']);
        Treatment::create([
            'paciente' => 'Paciente base',
            'tratamiento' => 'Limpieza',
            'tipo' => 'Preventivo',
            'fecha' => now()->toDateString(),
        ]);
        $date = now()->next('Monday')->toDateString();
        Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'date' => $date,
            'time' => '10:00:00',
            'tipo' => 'Limpieza',
            'estado' => 'Pendiente',
        ]);

        $response = $this->withSession([
            'access_role' => 'asistente',
        ])->post(route('citas.store'), [
            'paciente' => 'Paciente nuevo',
            'dentista' => $dentist->nombre,
            'fecha' => $date,
            'hora' => '10:30',
            'tipo' => 'Limpieza',
        ]);

        $response->assertSessionHasErrors('hora');
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_different_dentists_can_schedule_same_time_for_different_patients(): void
    {
        $firstPatient = Patient::create(['nombre' => 'Paciente Doctor Uno', 'telefono' => '2220000001', 'estado' => 'Activo']);
        $secondPatient = Patient::create(['nombre' => 'Paciente Doctor Dos', 'telefono' => '2220000002', 'estado' => 'Activo']);
        $firstDentist = Dentist::create(['nombre' => 'Dra. Paralela Uno', 'telefono' => '2220000003']);
        $secondDentist = Dentist::create(['nombre' => 'Dr. Paralelo Dos', 'telefono' => '2220000004']);
        Treatment::create([
            'paciente' => 'Catalogo',
            'tratamiento' => 'Consulta paralela',
            'tipo' => 'General',
            'fecha' => now()->toDateString(),
        ]);
        $date = now()->next('Monday')->toDateString();

        Appointment::create([
            'patient_id' => $firstPatient->id,
            'dentist_id' => $firstDentist->id,
            'date' => $date,
            'time' => '10:00:00',
            'end_time' => '11:00:00',
            'tipo' => 'Consulta paralela',
            'estado' => 'Pendiente',
        ]);

        $response = $this->withSession(['access_role' => 'asistente'])->post(route('citas.store'), [
            'paciente' => $secondPatient->nombre,
            'dentista' => $secondDentist->nombre,
            'fecha' => $date,
            'hora' => '10:00',
            'hora_fin' => '11:00',
            'tipo' => 'Consulta paralela',
        ]);

        $response->assertRedirect(route('citas'));
        $this->assertDatabaseHas('appointments', [
            'patient_id' => $secondPatient->id,
            'dentist_id' => $secondDentist->id,
            'date' => $date.' 00:00:00',
            'time' => '10:00:00',
        ]);
    }

    public function test_same_patient_cannot_be_scheduled_with_another_dentist_at_same_time(): void
    {
        $patient = Patient::create(['nombre' => 'Paciente Doble Agenda', 'telefono' => '2220000010', 'estado' => 'Activo']);
        $firstDentist = Dentist::create(['nombre' => 'Dra. Doble Uno', 'telefono' => '2220000011']);
        $secondDentist = Dentist::create(['nombre' => 'Dr. Doble Dos', 'telefono' => '2220000012']);
        Treatment::create([
            'paciente' => 'Catalogo',
            'tratamiento' => 'Consulta doble agenda',
            'tipo' => 'General',
            'fecha' => now()->toDateString(),
        ]);
        $date = now()->next('Monday')->toDateString();

        Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $firstDentist->id,
            'date' => $date,
            'time' => '10:00:00',
            'end_time' => '11:00:00',
            'tipo' => 'Consulta doble agenda',
            'estado' => 'Pendiente',
        ]);

        $response = $this->withSession(['access_role' => 'asistente'])->post(route('citas.store'), [
            'paciente' => $patient->nombre,
            'dentista' => $secondDentist->nombre,
            'fecha' => $date,
            'hora' => '10:30',
            'hora_fin' => '11:30',
            'tipo' => 'Consulta doble agenda',
        ]);

        $response->assertSessionHasErrors('hora');
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_finished_appointments_do_not_block_new_schedule(): void
    {
        $patient = Patient::create(['nombre' => 'Paciente Cita Terminada', 'telefono' => '2220000020', 'estado' => 'Activo']);
        $dentist = Dentist::create(['nombre' => 'Dra. Cita Terminada', 'telefono' => '2220000021']);
        Treatment::create([
            'paciente' => 'Catalogo',
            'tratamiento' => 'Consulta despues terminada',
            'tipo' => 'General',
            'fecha' => now()->toDateString(),
        ]);
        $date = now()->next('Monday')->toDateString();

        Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'date' => $date,
            'time' => '10:00:00',
            'end_time' => '11:00:00',
            'tipo' => 'Consulta despues terminada',
            'estado' => 'Terminada',
        ]);

        $response = $this->withSession(['access_role' => 'asistente'])->post(route('citas.store'), [
            'paciente' => $patient->nombre,
            'dentista' => $dentist->nombre,
            'fecha' => $date,
            'hora' => '10:30',
            'hora_fin' => '11:30',
            'tipo' => 'Consulta despues terminada',
        ]);

        $response->assertRedirect(route('citas'));
        $this->assertDatabaseCount('appointments', 2);
        $this->assertDatabaseHas('appointments', [
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'estado' => 'Pendiente',
            'time' => '10:30:00',
        ]);
    }

    public function test_same_day_appointment_cannot_be_scheduled_after_time_has_passed(): void
    {
        $patient = Patient::create(['nombre' => 'Paciente imprevisto', 'telefono' => '2220000000', 'estado' => 'Activo']);
        $dentist = Dentist::create(['nombre' => 'Dra. Disponible', 'telefono' => '2220000000']);
        Treatment::create([
            'paciente' => $patient->nombre,
            'tratamiento' => 'Consulta urgente',
            'tipo' => 'Preventivo',
            'fecha' => now()->toDateString(),
        ]);

        $time = now()->subHour()->format('H:i');

        $response = $this->withSession(['access_role' => 'asistente'])->post(route('citas.store'), [
            'paciente' => $patient->nombre,
            'dentista' => $dentist->nombre,
            'fecha' => now()->toDateString(),
            'hora' => $time,
            'tipo' => 'Consulta urgente',
        ]);

        $response->assertSessionHasErrors('hora');
        $this->assertDatabaseMissing('appointments', [
            'dentist_id' => $dentist->id,
            'date' => now()->toDateString().' 00:00:00',
            'time' => "{$time}:00",
        ]);

        $this->withSession(['access_role' => 'asistente'])
            ->get(route('citas.create'))
            ->assertOk()
            ->assertSee('data-error-for="hora"', false)
            ->assertSee($patient->nombre)
            ->assertSee('value="'.$time.'"', false);
    }

    public function test_doctor_can_schedule_an_appointment_at_the_current_minute(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-06 10:15:45'));

        try {
            $user = User::factory()->create(['name' => 'Dra. Tiempo Real', 'role' => 'doctor']);
            $dentist = Dentist::create([
                'user_id' => $user->id,
                'nombre' => $user->name,
                'telefono' => '2220000000',
            ]);
            $patient = Patient::create(['nombre' => 'Paciente Tiempo Real', 'telefono' => '2220000000', 'estado' => 'Activo']);
            Treatment::create([
                'paciente' => $patient->nombre,
                'tratamiento' => 'Consulta actual',
                'tipo' => 'General',
                'fecha' => now()->toDateString(),
            ]);

            $response = $this->withSession([
                'access_role' => 'doctor',
                'access_user_id' => $user->id,
                'access_email' => $user->email,
                'access_name' => $user->name,
            ])->post(route('citas.store'), [
                'paciente' => $patient->nombre,
                'fecha' => now()->toDateString(),
                'hora' => '10:15',
                'hora_fin' => '10:45',
                'tipo' => 'Consulta actual',
            ]);

            $response->assertRedirect(route('citas'));
            $this->assertDatabaseHas('appointments', [
                'patient_id' => $patient->id,
                'dentist_id' => $dentist->id,
                'date' => now()->toDateString().' 00:00:00',
                'time' => '10:15:00',
                'end_time' => '10:45:00',
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_appointment_accepts_quarter_hour_times_inside_dentist_schedule(): void
    {
        $patient = Patient::create(['nombre' => 'Paciente horario', 'telefono' => '2220000000', 'estado' => 'Activo']);
        $dentist = Dentist::create(['nombre' => 'Dra. Horario', 'telefono' => '2220000000']);
        Treatment::create([
            'paciente' => $patient->nombre,
            'tratamiento' => 'Limpieza',
            'tipo' => 'Preventivo',
            'fecha' => now()->toDateString(),
        ]);

        $date = now()->next('Monday')->toDateString();

        $response = $this->withSession(['access_role' => 'asistente'])->post(route('citas.store'), [
            'paciente' => $patient->nombre,
            'dentista' => $dentist->nombre,
            'fecha' => $date,
            'hora' => '13:15',
            'hora_fin' => '13:47',
            'tipo' => 'Limpieza',
        ]);

        $response->assertRedirect(route('citas'));
        $this->assertDatabaseHas('appointments', [
            'dentist_id' => $dentist->id,
            'date' => $date.' 00:00:00',
            'time' => '13:15:00',
            'end_time' => '13:47:00',
        ]);
    }

    public function test_appointment_uses_treatment_color_when_creating_appointment(): void
    {
        $patient = Patient::create(['nombre' => 'Paciente color asistente', 'telefono' => '2220000000', 'estado' => 'Activo']);
        $dentist = Dentist::create(['nombre' => 'Dra. Color Asistente', 'telefono' => '2220000000']);
        Treatment::create([
            'paciente' => $patient->nombre,
            'tratamiento' => 'Consulta color',
            'tipo' => 'Preventivo',
            'color' => '#16a34a',
            'fecha' => now()->toDateString(),
        ]);
        $date = now()->next('Monday')->toDateString();

        $this->withSession(['access_role' => 'asistente'])
            ->get(route('citas.create'))
            ->assertOk()
            ->assertDontSee('Color en el calendario')
            ->assertDontSee('name="color"', false);

        $response = $this->withSession(['access_role' => 'asistente'])->post(route('citas.store'), [
            'paciente' => $patient->nombre,
            'dentista' => $dentist->nombre,
            'fecha' => $date,
            'hora' => '10:00',
            'hora_fin' => '11:00',
            'tipo' => 'Consulta color',
        ]);

        $response->assertRedirect(route('citas'));
        $this->assertDatabaseHas('appointments', [
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'color' => '#16a34a',
        ]);
    }

    public function test_updating_appointment_uses_selected_treatment_color_without_financial_adjustments_for_assistant(): void
    {
        $patient = Patient::create(['nombre' => 'Paciente color editar', 'telefono' => '2220000000', 'estado' => 'Activo']);
        $dentist = Dentist::create(['nombre' => 'Dra. Color Editar', 'telefono' => '2220000000']);
        Treatment::create([
            'paciente' => $patient->nombre,
            'tratamiento' => 'Consulta editar color',
            'tipo' => 'Preventivo',
            'color' => '#f59e0b',
            'fecha' => now()->toDateString(),
        ]);
        $date = now()->next('Monday')->toDateString();
        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'date' => $date,
            'time' => '10:00:00',
            'end_time' => '11:00:00',
            'tipo' => 'Consulta editar color',
            'estado' => 'Pendiente',
            'color' => '#9700cf',
        ]);

        $this->withSession(['access_role' => 'asistente'])
            ->get(route('citas.edit', $appointment->id))
            ->assertOk()
            ->assertDontSee('Color en el calendario')
            ->assertDontSee('name="color"', false)
            ->assertDontSee('Ajuste econ');

        $response = $this->withSession(['access_role' => 'asistente'])->post(route('citas.update', $appointment->id), [
            'paciente' => $patient->nombre,
            'dentista' => $dentist->nombre,
            'fecha' => $date,
            'hora' => '10:00',
            'hora_fin' => '11:00',
            'tipo' => 'Consulta editar color',
            'estado' => 'Confirmada',
        ]);

        $response->assertRedirect(route('citas'));
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'estado' => 'Confirmada',
            'color' => '#f59e0b',
        ]);
    }

    public function test_assistant_appointments_are_saved_by_creator_and_visible_to_other_assistants(): void
    {
        $assistantOne = User::factory()->create(['role' => 'asistente', 'name' => 'Asistente Uno']);
        $assistantTwo = User::factory()->create(['role' => 'asistente', 'name' => 'Asistente Dos']);
        $dentist = Dentist::create(['nombre' => 'Dra. Usuarios Citas', 'telefono' => '2220000000']);
        $firstPatient = Patient::create(['nombre' => 'Paciente Asistente Uno', 'telefono' => '2220000001', 'estado' => 'Activo']);
        $secondPatient = Patient::create(['nombre' => 'Paciente Asistente Dos', 'telefono' => '2220000002', 'estado' => 'Activo']);
        Treatment::create([
            'paciente' => 'Catalogo',
            'tratamiento' => 'Consulta usuario',
            'tipo' => 'General',
            'fecha' => now()->toDateString(),
        ]);
        $date = now()->next('Monday')->toDateString();

        $this->withSession(['access_role' => 'asistente', 'access_user_id' => $assistantOne->id])
            ->post(route('citas.store'), [
                'paciente' => $firstPatient->nombre,
                'dentista' => $dentist->nombre,
                'fecha' => $date,
                'hora' => '10:00',
                'hora_fin' => '11:00',
                'tipo' => 'Consulta usuario',
            ])
            ->assertRedirect(route('citas'));

        $this->withSession(['access_role' => 'asistente', 'access_user_id' => $assistantTwo->id])
            ->post(route('citas.store'), [
                'paciente' => $secondPatient->nombre,
                'dentista' => $dentist->nombre,
                'fecha' => $date,
                'hora' => '11:00',
                'hora_fin' => '12:00',
                'tipo' => 'Consulta usuario',
            ])
            ->assertRedirect(route('citas'));

        $this->assertDatabaseHas('appointments', [
            'patient_id' => $firstPatient->id,
            'created_by_user_id' => $assistantOne->id,
        ]);
        $this->assertDatabaseHas('appointments', [
            'patient_id' => $secondPatient->id,
            'created_by_user_id' => $assistantTwo->id,
        ]);

        $this->withSession(['access_role' => 'asistente', 'access_user_id' => $assistantOne->id])
            ->get(route('citas'))
            ->assertOk()
            ->assertSee('Paciente Asistente Uno')
            ->assertSee('Paciente Asistente Dos');

        $this->withSession(['access_role' => 'asistente', 'access_user_id' => $assistantTwo->id])
            ->get(route('citas'))
            ->assertOk()
            ->assertSee('Paciente Asistente Uno')
            ->assertSee('Paciente Asistente Dos');
    }

    public function test_assistant_cannot_edit_another_assistants_appointment(): void
    {
        $assistantOne = User::factory()->create(['role' => 'asistente']);
        $assistantTwo = User::factory()->create(['role' => 'asistente']);
        $patient = Patient::create(['nombre' => 'Paciente Privado Asistente', 'telefono' => '2220000000', 'estado' => 'Activo']);
        $dentist = Dentist::create(['nombre' => 'Dra. Privacidad', 'telefono' => '2220000000']);
        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'created_by_user_id' => $assistantOne->id,
            'date' => now()->next('Monday')->toDateString(),
            'time' => '10:00:00',
            'end_time' => '11:00:00',
            'tipo' => 'Consulta privada',
            'estado' => 'Pendiente',
        ]);

        $this->withSession(['access_role' => 'asistente', 'access_user_id' => $assistantTwo->id])
            ->get(route('citas.edit', $appointment->id))
            ->assertForbidden();
    }

    public function test_appointment_cannot_be_scheduled_outside_dentist_schedule(): void
    {
        $patient = Patient::create(['nombre' => 'Paciente fuera horario', 'telefono' => '2220000000', 'estado' => 'Activo']);
        $dentist = Dentist::create(['nombre' => 'Dra. Sin horario', 'telefono' => '2220000000']);
        Treatment::create([
            'paciente' => $patient->nombre,
            'tratamiento' => 'Consulta fuera horario',
            'tipo' => 'Preventivo',
            'fecha' => now()->toDateString(),
        ]);

        $date = now()->next('Monday')->toDateString();

        $response = $this->withSession(['access_role' => 'asistente'])->post(route('citas.store'), [
            'paciente' => $patient->nombre,
            'dentista' => $dentist->nombre,
            'fecha' => $date,
            'hora' => '18:15',
            'hora_fin' => '18:45',
            'tipo' => 'Consulta fuera horario',
        ]);

        $response->assertSessionHasErrors('hora');
        $response->assertSessionHasErrors([
            'hora' => 'El dentista trabaja de 09:00 AM a 05:00 PM; la hora solicitada no está disponible.',
        ]);
        $this->assertDatabaseMissing('appointments', [
            'dentist_id' => $dentist->id,
            'date' => $date.' 00:00:00',
            'time' => '18:15:00',
        ]);
    }

    public function test_appointment_shows_an_inactive_day_message_when_dentist_does_not_work(): void
    {
        $patient = Patient::create(['nombre' => 'Paciente día inhábil', 'telefono' => '2220000000', 'estado' => 'Activo']);
        $dentist = Dentist::create(['nombre' => 'Dra. Día inhábil', 'telefono' => '2220000000']);
        Treatment::create([
            'paciente' => $patient->nombre,
            'tratamiento' => 'Consulta día inhábil',
            'tipo' => 'Preventivo',
            'fecha' => now()->toDateString(),
        ]);

        $date = now()->next('Sunday')->toDateString();

        $response = $this->withSession(['access_role' => 'asistente'])->post(route('citas.store'), [
            'paciente' => $patient->nombre,
            'dentista' => $dentist->nombre,
            'fecha' => $date,
            'hora' => '10:00',
            'hora_fin' => '11:00',
            'tipo' => 'Consulta día inhábil',
        ]);

        $response->assertSessionHasErrors([
            'hora' => 'El dentista no trabaja el domingo; es un día inhábil.',
        ]);
    }

    public function test_appointment_end_time_must_be_after_start_time(): void
    {
        $patient = Patient::create(['nombre' => 'Paciente rango invalido', 'telefono' => '2220000000', 'estado' => 'Activo']);
        $dentist = Dentist::create(['nombre' => 'Dra. Rango', 'telefono' => '2220000000']);
        Treatment::create([
            'paciente' => $patient->nombre,
            'tratamiento' => 'Consulta',
            'tipo' => 'Preventivo',
            'fecha' => now()->toDateString(),
        ]);

        $response = $this->withSession(['access_role' => 'asistente'])->post(route('citas.store'), [
            'paciente' => $patient->nombre,
            'dentista' => $dentist->nombre,
            'fecha' => now()->addDay()->toDateString(),
            'hora' => '15:30',
            'hora_fin' => '15:10',
            'tipo' => 'Consulta',
        ]);

        $response->assertSessionHasErrors('hora_fin');
        $this->assertDatabaseMissing('appointments', [
            'dentist_id' => $dentist->id,
            'time' => '15:30:00',
        ]);
    }

    public function test_reprogram_route_redirects_to_edit_form(): void
    {
        $patient = Patient::create(['nombre' => 'Paciente reprograma', 'telefono' => '2220000000', 'estado' => 'Activo']);
        $dentist = Dentist::create(['nombre' => 'Dra. Reprograma', 'telefono' => '2220000000']);
        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'date' => now()->addDay()->toDateString(),
            'time' => '10:00:00',
            'tipo' => 'Limpieza',
            'estado' => 'Pendiente',
        ]);

        $response = $this->withSession(['access_role' => 'asistente'])->get(route('citas.reprogramar', $appointment));

        $response->assertRedirect(route('citas.edit', ['id' => $appointment->id]));
    }

    public function test_calendar_drag_move_rejects_past_dates(): void
    {
        $patient = Patient::create(['nombre' => 'Paciente fecha pasada', 'telefono' => '2220000000', 'estado' => 'Activo']);
        $dentist = Dentist::create(['nombre' => 'Dra. Fecha Pasada', 'telefono' => '2220000000']);
        Treatment::create([
            'paciente' => '',
            'tratamiento' => 'Consulta mover pasada',
            'tipo' => 'Preventivo',
            'fecha' => now()->toDateString(),
            'estado' => 'Activo',
            'costo' => 500,
        ]);
        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'date' => now()->next('Monday')->toDateString(),
            'time' => '10:00:00',
            'end_time' => '11:00:00',
            'tipo' => 'Consulta mover pasada',
            'estado' => 'Pendiente',
        ]);

        $this->withSession(['access_role' => 'asistente'])
            ->postJson(route('citas.calendar.move', $appointment), ['fecha' => now()->subDay()->toDateString()])
            ->assertUnprocessable();

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'date' => $appointment->date->toDateString().' 00:00:00',
        ]);
    }

    public function test_calendar_drag_move_rejects_overlapping_dentist_appointments(): void
    {
        $firstPatient = Patient::create(['nombre' => 'Paciente mover uno', 'telefono' => '2220000001', 'estado' => 'Activo']);
        $secondPatient = Patient::create(['nombre' => 'Paciente mover dos', 'telefono' => '2220000002', 'estado' => 'Activo']);
        $dentist = Dentist::create(['nombre' => 'Dra. Choque Drag', 'telefono' => '2220000003']);
        Treatment::create([
            'paciente' => '',
            'tratamiento' => 'Consulta drag choque',
            'tipo' => 'Preventivo',
            'fecha' => now()->toDateString(),
            'estado' => 'Activo',
            'costo' => 500,
        ]);
        $sourceDate = now()->next('Monday')->toDateString();
        $targetDate = now()->next('Tuesday')->toDateString();
        $appointment = Appointment::create([
            'patient_id' => $firstPatient->id,
            'dentist_id' => $dentist->id,
            'date' => $sourceDate,
            'time' => '10:00:00',
            'end_time' => '11:00:00',
            'tipo' => 'Consulta drag choque',
            'estado' => 'Pendiente',
        ]);
        Appointment::create([
            'patient_id' => $secondPatient->id,
            'dentist_id' => $dentist->id,
            'date' => $targetDate,
            'time' => '10:30:00',
            'end_time' => '11:30:00',
            'tipo' => 'Consulta drag choque',
            'estado' => 'Pendiente',
        ]);

        $this->withSession(['access_role' => 'asistente'])
            ->postJson(route('citas.calendar.move', $appointment), ['fecha' => $targetDate])
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'El dentista ya tiene una cita de 10:30 a 11:30. Elige una hora de inicio y termino fuera de ese bloque.']);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'date' => $sourceDate.' 00:00:00',
        ]);
    }

    public function test_calendar_drag_move_updates_date_when_slot_is_available(): void
    {
        $patient = Patient::create(['nombre' => 'Paciente mover libre', 'telefono' => '2220000000', 'estado' => 'Activo']);
        $dentist = Dentist::create(['nombre' => 'Dra. Mover Libre', 'telefono' => '2220000000']);
        Treatment::create([
            'paciente' => '',
            'tratamiento' => 'Consulta mover libre',
            'tipo' => 'Preventivo',
            'fecha' => now()->toDateString(),
            'estado' => 'Activo',
            'costo' => 500,
        ]);
        $targetDate = now()->next('Tuesday')->toDateString();
        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'date' => now()->next('Monday')->toDateString(),
            'time' => '10:00:00',
            'end_time' => '11:00:00',
            'tipo' => 'Consulta mover libre',
            'estado' => 'Pendiente',
        ]);

        $this->withSession(['access_role' => 'asistente'])
            ->postJson(route('citas.calendar.move', $appointment), ['fecha' => $targetDate])
            ->assertOk()
            ->assertJsonFragment(['message' => 'Cita reprogramada correctamente.']);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'date' => $targetDate.' 00:00:00',
        ]);
    }

    public function test_overdue_appointment_is_completed_and_hidden_from_active_agenda(): void
    {
        $patient = Patient::create(['nombre' => 'Paciente vencido', 'telefono' => '2220000000', 'estado' => 'Activo']);
        $dentist = Dentist::create(['nombre' => 'Dra. Automatico', 'telefono' => '2220000000']);
        Treatment::create([
            'paciente' => '',
            'tratamiento' => 'Limpieza automatica',
            'tipo' => 'Preventivo',
            'fecha' => now()->toDateString(),
            'estado' => 'Activo',
            'costo' => 500,
        ]);
        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'date' => now()->toDateString(),
            'time' => now()->subHours(2)->format('H:i:s'),
            'end_time' => now()->subHour()->format('H:i:s'),
            'tipo' => 'Limpieza automatica',
            'estado' => 'Confirmada',
        ]);

        $this->withSession(['access_role' => 'asistente'])
            ->get(route('citas'))
            ->assertOk()
            ->assertDontSee('Paciente vencido');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'estado' => 'Terminada',
        ]);
        $this->assertDatabaseHas('treatments', [
            'appointment_id' => $appointment->id,
            'estado' => 'Realizado',
            'costo' => 500,
        ]);
    }

    public function test_finishing_appointment_registers_doctor_earnings_once(): void
    {
        $user = User::factory()->create(['name' => 'Dra. Ganancias', 'role' => 'doctor']);
        $dentist = Dentist::create([
            'user_id' => $user->id,
            'nombre' => $user->name,
            'telefono' => '2220000090',
        ]);
        $patient = Patient::create([
            'nombre' => 'Paciente terminado',
            'telefono' => '2220000091',
            'estado' => 'Activo',
        ]);
        Treatment::create([
            'paciente' => '',
            'tratamiento' => 'Limpieza final',
            'tipo' => 'Preventivo',
            'fecha' => now()->toDateString(),
            'estado' => 'Activo',
            'costo' => 650,
        ]);
        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'date' => now()->toDateString(),
            'time' => '10:00:00',
            'tipo' => 'Limpieza final',
            'estado' => 'Confirmada',
            'descuento' => 10,
            'procedimiento_extra' => 'Radiografia adicional',
            'costo_extra' => 200,
        ]);
        $session = [
            'access_role' => 'doctor',
            'access_user_id' => $user->id,
            'access_email' => $user->email,
        ];

        $this->withSession($session)->post(route('citas.terminar', $appointment))
            ->assertRedirect(route('citas'));
        $this->withSession($session)->post(route('citas.terminar', $appointment))
            ->assertRedirect(route('citas'));

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'estado' => 'Terminada',
        ]);
        $this->assertDatabaseHas('treatments', [
            'appointment_id' => $appointment->id,
            'estado' => 'Realizado',
            'costo' => 765,
        ]);
        $this->assertStringContainsString(
            'Radiografia adicional',
            Treatment::where('appointment_id', $appointment->id)->value('descripcion')
        );
        $this->assertSame(1, Treatment::where('appointment_id', $appointment->id)->count());

        $this->withSession($session)
            ->get(route('reportes', ['periodo' => 'Este mes']))
            ->assertOk()
            ->assertSee('$765');
    }

    public function test_finishing_appointment_accepts_completion_adjustments(): void
    {
        $user = User::factory()->create(['name' => 'Dra. Ajuste Final', 'role' => 'doctor']);
        $dentist = Dentist::create([
            'user_id' => $user->id,
            'nombre' => $user->name,
            'telefono' => '2220000190',
        ]);
        $patient = Patient::create([
            'nombre' => 'Paciente ajuste final',
            'telefono' => '2220000191',
            'estado' => 'Activo',
        ]);
        Treatment::create([
            'paciente' => '',
            'tratamiento' => 'Sellador final',
            'tipo' => 'Preventivo',
            'fecha' => now()->toDateString(),
            'estado' => 'Activo',
            'costo' => 500,
        ]);
        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'date' => now()->toDateString(),
            'time' => '12:00:00',
            'tipo' => 'Sellador final',
            'estado' => 'Confirmada',
        ]);

        $this->withSession([
            'access_role' => 'doctor',
            'access_user_id' => $user->id,
            'access_email' => $user->email,
        ])->post(route('citas.terminar', $appointment), [
            'descuento' => 10,
            'procedimiento_extra' => 'Pulido adicional',
            'costo_extra' => 100,
            'completion_description' => 'Se aplico sellador y se dieron indicaciones posteriores.',
        ])->assertRedirect(route('citas'));

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'descuento' => 10,
            'procedimiento_extra' => 'Pulido adicional',
            'costo_extra' => 100,
            'completion_description' => 'Se aplico sellador y se dieron indicaciones posteriores.',
            'estado' => 'Terminada',
        ]);
        $this->assertDatabaseHas('treatments', [
            'appointment_id' => $appointment->id,
            'estado' => 'Realizado',
            'costo' => 540,
        ]);
        $this->assertStringContainsString(
            'Se aplico sellador y se dieron indicaciones posteriores.',
            Treatment::where('appointment_id', $appointment->id)->value('descripcion')
        );
    }

    public function test_cancel_appointment_stores_cancellation_reason(): void
    {
        $user = User::factory()->create(['name' => 'Dra. Cancela', 'role' => 'doctor']);
        $dentist = Dentist::create([
            'user_id' => $user->id,
            'nombre' => $user->name,
            'telefono' => '2220000290',
        ]);
        $patient = Patient::create([
            'nombre' => 'Paciente cancelacion',
            'telefono' => '2220000291',
            'estado' => 'Activo',
        ]);
        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'date' => now()->addDay()->toDateString(),
            'time' => '13:00:00',
            'tipo' => 'Revision',
            'estado' => 'Pendiente',
        ]);

        $this->withSession([
            'access_role' => 'doctor',
            'access_user_id' => $user->id,
            'access_email' => $user->email,
        ])->post(route('citas.cancelar', $appointment), [
            'cancellation_reason' => 'Paciente solicito reprogramar.',
        ])->assertRedirect(route('citas'));

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'estado' => 'Cancelada',
            'cancellation_reason' => 'Paciente solicito reprogramar.',
        ]);
    }

    public function test_assistant_can_cancel_an_appointment_created_by_another_assistant(): void
    {
        $creator = User::factory()->create(['role' => 'asistente']);
        $assistant = User::factory()->create(['role' => 'asistente']);
        $dentist = Dentist::create(['nombre' => 'Dra. Agenda Compartida', 'telefono' => '2220000292']);
        $patient = Patient::create(['nombre' => 'Ronaldo', 'telefono' => '2220000293', 'estado' => 'Activo']);
        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'created_by_user_id' => $creator->id,
            'date' => now()->addDay()->toDateString(),
            'time' => '14:00:00',
            'tipo' => 'Revision',
            'estado' => 'Pendiente',
        ]);

        $this->withSession([
            'access_role' => 'asistente',
            'access_user_id' => $assistant->id,
        ])->post(route('citas.cancelar', $appointment), [
            'cancellation_reason' => 'Cancelada desde la agenda compartida.',
        ])->assertRedirect(route('citas'));

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'estado' => 'Cancelada',
        ]);
        $this->assertDatabaseHas('patients', ['id' => $patient->id]);
    }

    public function test_doctor_only_sees_own_appointments_in_appointments_section(): void
    {
        $user = User::factory()->create(['name' => 'Dra. Agenda Privada', 'role' => 'doctor']);
        $ownDentist = Dentist::create([
            'user_id' => $user->id,
            'nombre' => $user->name,
            'telefono' => '2220000080',
        ]);
        $otherDentist = Dentist::create([
            'nombre' => 'Dr. Agenda Ajena',
            'telefono' => '2220000081',
        ]);
        $ownPatient = Patient::create(['nombre' => 'Paciente propio visible', 'telefono' => '2220000082', 'estado' => 'Activo']);
        $otherPatient = Patient::create(['nombre' => 'Paciente ajeno oculto', 'telefono' => '2220000083', 'estado' => 'Activo']);

        Appointment::create([
            'patient_id' => $ownPatient->id,
            'dentist_id' => $ownDentist->id,
            'date' => now()->addDay()->toDateString(),
            'time' => '10:00:00',
            'tipo' => 'Consulta propia',
            'estado' => 'Confirmada',
        ]);
        Appointment::create([
            'patient_id' => $otherPatient->id,
            'dentist_id' => $otherDentist->id,
            'date' => now()->addDay()->toDateString(),
            'time' => '11:00:00',
            'tipo' => 'Consulta ajena',
            'estado' => 'Confirmada',
        ]);

        $this->withSession([
            'access_role' => 'doctor',
            'access_user_id' => $user->id,
            'access_email' => $user->email,
        ])->get(route('citas'))
            ->assertOk()
            ->assertSee('Paciente propio visible')
            ->assertDontSee('Paciente ajeno oculto')
            ->assertDontSee('Dr. Agenda Ajena');
    }

    public function test_doctor_cannot_change_the_dentist_when_editing_own_appointment(): void
    {
        $user = User::factory()->create(['name' => 'Dra. Cita Fija', 'role' => 'doctor']);
        $ownDentist = Dentist::create([
            'user_id' => $user->id,
            'nombre' => $user->name,
            'telefono' => '2220000090',
        ]);
        $otherDentist = Dentist::create([
            'nombre' => 'Dr. No Permitido',
            'telefono' => '2220000091',
        ]);
        $patient = Patient::create([
            'nombre' => 'Paciente Cita Fija',
            'telefono' => '2220000092',
            'estado' => 'Activo',
        ]);
        Treatment::create([
            'paciente' => 'Catalogo',
            'tratamiento' => 'Consulta fija',
            'tipo' => 'General',
            'fecha' => now()->toDateString(),
        ]);
        $date = now()->next('Monday')->toDateString();
        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $ownDentist->id,
            'date' => $date,
            'time' => '10:00:00',
            'end_time' => '11:00:00',
            'tipo' => 'Consulta fija',
            'estado' => 'Pendiente',
        ]);
        $session = [
            'access_role' => 'doctor',
            'access_user_id' => $user->id,
            'access_email' => $user->email,
        ];

        $this->withSession($session)
            ->get(route('citas.edit', $appointment->id))
            ->assertOk()
            ->assertSee('Doctor de la sesi', false)
            ->assertDontSee('name="dentista"', false)
            ->assertSee('Dr. No Permitido');

        $this->withSession($session)->post(route('citas.update', $appointment->id), [
            'paciente' => $patient->nombre,
            'dentista' => $otherDentist->nombre,
            'fecha' => $date,
            'hora' => '10:00',
            'hora_fin' => '11:00',
            'tipo' => 'Consulta fija',
            'estado' => 'Confirmada',
            'color' => '#9700cf',
        ])->assertRedirect(route('citas'));

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'dentist_id' => $ownDentist->id,
            'estado' => 'Confirmada',
        ]);
    }

    public function test_collaborative_appointment_is_added_to_both_agendas_and_blocks_both_doctors(): void
    {
        $doctor = User::factory()->create(['name' => 'Dra. Colaboradora Uno', 'role' => 'doctor']);
        $otherUser = User::factory()->create(['name' => 'Dr. Colaborador Dos', 'role' => 'doctor']);
        $primary = Dentist::create(['user_id' => $doctor->id, 'nombre' => $doctor->name, 'telefono' => '2221111101']);
        $collaborator = Dentist::create(['user_id' => $otherUser->id, 'nombre' => $otherUser->name, 'telefono' => '2221111102']);
        $patient = Patient::create(['nombre' => 'Paciente Colaborativo', 'telefono' => '2221111103', 'estado' => 'Activo']);
        Treatment::create([
            'paciente' => '',
            'tratamiento' => 'Cirugia colaborativa',
            'tipo' => 'Cirugia',
            'fecha' => now()->toDateString(),
            'estado' => 'Activo',
            'costo' => 1800,
        ]);
        $date = now()->next('Monday')->toDateString();
        $session = [
            'access_role' => 'doctor',
            'access_user_id' => $doctor->id,
            'access_email' => $doctor->email,
        ];

        $this->withSession($session)->post(route('citas.store'), [
            'paciente' => $patient->nombre,
            'fecha' => $date,
            'hora' => '10:00',
            'hora_fin' => '11:00',
            'tipo' => 'Cirugia colaborativa',
            'collaborator_dentist_id' => $collaborator->id,
        ])->assertRedirect(route('citas'));

        $appointment = Appointment::where('patient_id', $patient->id)->firstOrFail();
        $this->assertSame($primary->id, $appointment->dentist_id);
        $this->assertDatabaseHas('appointment_dentist', [
            'appointment_id' => $appointment->id,
            'dentist_id' => $primary->id,
            'participation_role' => 'primary',
        ]);
        $this->assertDatabaseHas('appointment_dentist', [
            'appointment_id' => $appointment->id,
            'dentist_id' => $collaborator->id,
            'participation_role' => 'collaborator',
        ]);

        $this->withSession([
            'access_role' => 'doctor',
            'access_user_id' => $otherUser->id,
            'access_email' => $otherUser->email,
        ])->get(route('calendario', ['date' => $date]))
            ->assertOk()
            ->assertSee('Paciente Colaborativo');

        $secondPatient = Patient::create(['nombre' => 'Paciente En Conflicto', 'telefono' => '2221111104', 'estado' => 'Activo']);
        $this->withSession($session)->from(route('citas.create'))->post(route('citas.store'), [
            'paciente' => $secondPatient->nombre,
            'fecha' => $date,
            'hora' => '10:30',
            'hora_fin' => '11:30',
            'tipo' => 'Cirugia colaborativa',
            'collaborator_dentist_id' => $collaborator->id,
        ])->assertSessionHasErrors('hora');
    }
}
