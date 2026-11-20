<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Dentist;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientBackendTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_be_created_without_last_visit_and_is_shown_as_new(): void
    {
        $this->withSession(['access_role' => 'doctor'])
            ->post(route('pacientes.store'), [
                'nombre' => 'Paciente Sin Visita',
                'telefono' => '2220000000',
                'email' => '',
                'direccion' => 'Calle Principal 100, Puebla',
                'estado' => 'Activo',
            ])
            ->assertRedirect(route('pacientes'));

        $this->assertDatabaseHas('patients', [
            'nombre' => 'Paciente Sin Visita',
            'ultima_visita' => null,
        ]);

        $this->withSession(['access_role' => 'doctor'])
            ->get(route('pacientes'))
            ->assertOk()
            ->assertSee('Paciente Sin Visita')
            ->assertSee('Nuevo');
    }

    public function test_patient_duplicate_email_uses_readable_validation_message(): void
    {
        Patient::create([
            'nombre' => 'Paciente Existente',
            'telefono' => '2220000000',
            'email' => 'paciente@example.test',
            'estado' => 'Activo',
        ]);

        $response = $this->withSession(['access_role' => 'doctor'])
            ->from(route('pacientes.create'))
            ->post(route('pacientes.store'), [
                'nombre' => 'Paciente Duplicado',
                'telefono' => '2220000001',
                'email' => 'paciente@example.test',
                'estado' => 'Activo',
            ]);

        $response->assertRedirect(route('pacientes.create'));
        $response->assertSessionHasErrors(['email' => 'Ese correo ya esta registrado en otro paciente.']);
    }

    public function test_address_is_required_when_doctor_or_assistant_creates_a_patient(): void
    {
        foreach (['doctor', 'asistente'] as $index => $role) {
            $this->withSession(['access_role' => $role])
                ->from(route('pacientes.create'))
                ->post(route('pacientes.store'), [
                    'nombre' => 'Paciente Sin Direccion '.$role,
                    'telefono' => '222000001'.$index,
                    'estado' => 'Activo',
                ])
                ->assertRedirect(route('pacientes.create'))
                ->assertSessionHasErrors([
                    'direccion' => 'La dirección es obligatoria.',
                ]);
        }

        $this->assertDatabaseCount('patients', 0);
    }

    public function test_patient_name_and_phone_only_accept_allowed_characters(): void
    {
        $response = $this->withSession(['access_role' => 'doctor'])
            ->from(route('pacientes.create'))
            ->post(route('pacientes.store'), [
                'nombre' => 'Paciente 123!',
                'telefono' => '222-000-0000',
                'estado' => 'Activo',
            ]);

        $response->assertRedirect(route('pacientes.create'));
        $response->assertSessionHasErrors([
            'nombre' => 'El nombre completo solo debe contener letras y espacios.',
            'telefono' => 'El teléfono debe contener exactamente 10 números.',
        ]);
    }

    public function test_emergency_contact_name_and_phone_only_accept_allowed_characters(): void
    {
        $response = $this->withSession(['access_role' => 'doctor'])
            ->from(route('pacientes.create'))
            ->post(route('pacientes.store'), [
                'nombre' => 'Paciente Valido',
                'telefono' => '2220000000',
                'estado' => 'Activo',
                'contacto_emergencia' => [
                    'nombre' => 'Contacto 123!',
                    'telefono' => '222-000-0001',
                ],
            ]);

        $response->assertRedirect(route('pacientes.create'));
        $response->assertSessionHasErrors([
            'contacto_emergencia.nombre' => 'El nombre del contacto de emergencia solo debe contener letras y espacios.',
            'contacto_emergencia.telefono' => 'El teléfono del contacto de emergencia debe contener exactamente 10 números.',
        ]);
    }

    public function test_patient_phone_must_have_exactly_ten_digits(): void
    {
        foreach (['123456789', '12345678901'] as $index => $phone) {
            $this->withSession(['access_role' => $index === 0 ? 'doctor' : 'asistente'])
                ->from(route('pacientes.create'))
                ->post(route('pacientes.store'), [
                    'nombre' => 'Paciente Telefono Invalido',
                    'telefono' => $phone,
                    'direccion' => 'Calle Principal 100',
                    'estado' => 'Activo',
                ])
                ->assertRedirect(route('pacientes.create'))
                ->assertSessionHasErrors([
                    'telefono' => 'El teléfono debe contener exactamente 10 números.',
                ]);
        }

        $this->assertDatabaseCount('patients', 0);
    }

    public function test_assistant_can_open_expedientes_section(): void
    {
        Patient::create([
            'nombre' => 'Paciente Expediente',
            'telefono' => '2220000000',
            'estado' => 'Activo',
        ]);

        $this->withSession(['access_role' => 'asistente'])
            ->get(route('expedientes'))
            ->assertOk()
            ->assertSee('Expedientes')
            ->assertSee('Paciente Expediente');
    }

    public function test_patient_page_includes_delete_interaction_script(): void
    {
        $patient = Patient::create([
            'nombre' => 'Paciente Para Eliminar',
            'telefono' => '2220000000',
            'estado' => 'Activo',
        ]);

        $this->withSession(['access_role' => 'doctor'])
            ->get(route('pacientes'))
            ->assertOk()
            ->assertSee('js-delete-patient', false)
            ->assertSee('data-edit-url="'.route('pacientes.edit', $patient).'"', false)
            ->assertSee('aria-label="Eliminar paciente Paciente Para Eliminar"', false)
            ->assertDontSee('popovertarget="patient-actions', false)
            ->assertSee("method: 'DELETE'", false);
    }

    public function test_doctor_can_delete_a_patient(): void
    {
        $patient = Patient::create([
            'nombre' => 'Paciente Doctor Eliminar',
            'telefono' => '2220000001',
            'estado' => 'Activo',
        ]);

        $this->withSession(['access_role' => 'doctor'])
            ->deleteJson(route('pacientes.destroy', $patient))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('patients', ['id' => $patient->id]);
    }

    public function test_assistant_can_delete_a_patient(): void
    {
        $patient = Patient::create([
            'nombre' => 'Paciente Asistente Eliminar',
            'telefono' => '2220000002',
            'estado' => 'Activo',
        ]);

        $this->withSession(['access_role' => 'asistente'])
            ->deleteJson(route('pacientes.destroy', $patient))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('patients', ['id' => $patient->id]);
    }

    public function test_doctor_expedientes_are_scoped_to_own_patients(): void
    {
        $doctor = User::create([
            'name' => 'Dra. Expedientes',
            'email' => 'expedientes@example.test',
            'role' => 'doctor',
            'password' => 'password',
        ]);
        $ownDentist = Dentist::create(['user_id' => $doctor->id, 'nombre' => 'Dra. Expedientes', 'telefono' => '2220000000']);
        $otherDentist = Dentist::create(['nombre' => 'Dr. Ajeno', 'telefono' => '2220000001']);
        $ownPatient = Patient::create(['nombre' => 'Paciente Propio Expediente', 'telefono' => '2220000002', 'estado' => 'Activo']);
        $otherPatient = Patient::create(['nombre' => 'Paciente Ajeno Expediente', 'telefono' => '2220000003', 'estado' => 'Activo']);

        Appointment::create([
            'patient_id' => $ownPatient->id,
            'dentist_id' => $ownDentist->id,
            'date' => now()->toDateString(),
            'time' => '10:00:00',
            'tipo' => 'Consulta',
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
            ->get(route('expedientes'))
            ->assertOk()
            ->assertSee('Paciente Propio Expediente')
            ->assertDontSee('Paciente Ajeno Expediente');
    }

    public function test_new_record_flow_uses_record_labels_and_navigation(): void
    {
        $this->withSession(['access_role' => 'doctor'])
            ->get(route('expedientes'))
            ->assertOk()
            ->assertSee('Nuevo expediente')
            ->assertSee(route('pacientes.create', ['origen' => 'expedientes']), false);

        $this->withSession(['access_role' => 'doctor'])
            ->get(route('pacientes.create', ['origen' => 'expedientes']))
            ->assertOk()
            ->assertSee('Nuevo expediente')
            ->assertSee('Volver a expedientes')
            ->assertSee('name="origen" value="expedientes"', false);
    }

    public function test_creating_from_records_opens_the_new_patient_record(): void
    {
        $response = $this->withSession(['access_role' => 'doctor'])
            ->post(route('pacientes.store'), [
                'origen' => 'expedientes',
                'nombre' => 'Paciente Nuevo Expediente',
                'telefono' => '2221234567',
                'direccion' => 'Calle Clinica 123',
            ]);

        $patient = Patient::where('nombre', 'Paciente Nuevo Expediente')->firstOrFail();

        $response->assertRedirect(route('pacientes.expediente', [
            'paciente' => $patient,
            'tab' => 'resumen',
        ]));
        $response->assertSessionHas('success', 'Expediente creado correctamente.');
    }
}
