<?php

namespace Tests\Feature;

use App\Models\ClinicWorkspace;
use App\Models\Appointment;
use App\Models\Dentist;
use App\Models\Patient;
use App\Models\User;
use App\Support\ClinicContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClinicWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_expires_per_workspace_and_cannot_be_restarted(): void
    {
        $this->freezeSecond();
        $owner = User::factory()->create();
        $workspace = ClinicWorkspace::createForOwner($owner, 'Consultorio en prueba', trialRequired: true);
        $assistant = User::factory()->create();
        $workspace->members()->attach($assistant, ['role' => 'asistente']);

        $this->actingAs($assistant)->withSession(['clinic_workspace_id' => $workspace->id])
            ->post(route('clinic.plan.start'), ['plan' => 'basico'])->assertForbidden();
        $this->actingAs($owner)->withSession(['clinic_workspace_id' => $workspace->id])
            ->post(route('clinic.plan.start'), ['plan' => 'basico'])->assertRedirect(route('inicio'));
        $end = $workspace->fresh()->trial_ends_at;
        $this->assertTrue($end->equalTo(now()->addRealHours(72)));
        $this->travel(24)->hours();
        $this->post(route('clinic.plan.start'), ['plan' => 'consultorio'])->assertRedirect(route('inicio'));
        $this->assertSame('basico', $workspace->fresh()->trial_plan);
        $this->assertTrue($end->equalTo($workspace->fresh()->trial_ends_at));

        $otherOwner = User::factory()->create();
        $otherWorkspace = ClinicWorkspace::createForOwner($otherOwner, 'Otra agenda', trialRequired: true);
        $this->actingAs($otherOwner)->withSession(['clinic_workspace_id' => $otherWorkspace->id])
            ->post(route('clinic.plan.start'), ['plan' => 'consultorio'])->assertRedirect(route('inicio'));

        $this->travelTo($end);
        $this->actingAs($owner)->withSession(['clinic_workspace_id' => $workspace->id]);
        $this->get(route('citas.show', 999))->assertRedirect(route('clinic.plan'));
        $this->get(route('reportes.export'))->assertRedirect(route('clinic.plan'));
        $this->get(route('clinic.plan'))->assertOk()->assertSee('Tu prueba de 3 días terminó');
        $this->post(route('clinic.plan.start'), ['plan' => 'basico'])->assertRedirect(route('clinic.plan'));
        $this->actingAs($assistant)->get(route('clinic.plan'))->assertOk()->assertSee('Pídele al titular');
        $this->actingAs($otherOwner)->withSession(['clinic_workspace_id' => $otherWorkspace->id])
            ->get(route('inicio'))->assertOk();
    }

    public function test_basic_trial_limits_accepted_invitations_and_preserves_unused_link(): void
    {
        config(['mail.default' => 'log']);
        $owner = User::factory()->create();
        $workspace = ClinicWorkspace::createForOwner($owner, 'Consultorio básico', trialRequired: true);
        $this->actingAs($owner)->withSession(['clinic_workspace_id' => $workspace->id]);
        $this->post(route('clinic.plan.start'), ['plan' => 'basico'])->assertRedirect(route('inicio'));

        $urls = [];
        foreach (['primero@example.test', 'segundo@example.test'] as $email) {
            $this->post(route('admin.users.invite'), ['invite_email' => $email, 'invite_role' => 'asistente'])
                ->assertRedirect(route('admin.users'))->assertSessionHas('invite_link');
            $urls[] = session('invite_link');
        }

        auth()->logout();
        $this->flushSession();
        $form = [
            'name' => 'Asistente de prueba',
            'gender' => 'mujer',
            'password' => 'Registro123!',
            'password_confirmation' => 'Registro123!',
        ];
        $this->post($urls[0], $form)->assertRedirect(route('inicio'));
        auth()->logout();
        $this->flushSession();
        $this->postJson($urls[1], $form)->assertUnprocessable()->assertJsonValidationErrors('invitation');
        $this->assertSame(2, $workspace->members()->count());
        $this->assertDatabaseMissing('users', ['email' => 'segundo@example.test']);
        $this->assertTrue(DB::table('user_invitations')->where('email', 'segundo@example.test')->whereNull('used_at')->exists());
    }

    public function test_two_public_registrations_create_separate_agendas_and_owner_sessions(): void
    {
        $password = 'RegistroSeguro123!';
        $form = [
            'name' => 'Pedro',
            'last_name' => 'Ramírez',
            'clinic_name' => 'Consultorio de Pedro',
            'email' => 'PEDRO@example.test',
            'password' => $password,
            'password_confirmation' => $password,
        ];
        $this->post(route('register'), $form)->assertRedirect(route('clinic.plan'));
        $pedro = User::where('email', 'pedro@example.test')->firstOrFail();
        $pedroClinic = $pedro->clinicWorkspaces()->sole();
        $this->assertTrue($pedroClinic->owner->is($pedro));
        $this->assertSame('admin', $pedroClinic->pivot->role);
        $this->assertNull($pedroClinic->trial_ends_at);
        $this->get(route('inicio'))->assertRedirect(route('clinic.plan'));
        $this->get(route('clinic.plan'))->assertOk()->assertSee('Iniciar mis 3 días gratis');
        $this->post(route('clinic.plan.start'), ['plan' => 'basico'])->assertRedirect(route('inicio'));
        $this->get(route('inicio'))->assertOk()->assertSee('Consultorio de Pedro');
        $this->post(route('pacientes.store'), [
            'nombre' => 'Paciente Exclusivo de Pedro',
            'telefono' => '2220000000',
            'direccion' => 'Calle Principal 100, Puebla',
            'estado' => 'Activo',
        ])->assertRedirect(route('pacientes'));
        $this->assertDatabaseHas('patients', [
            'nombre' => 'Paciente Exclusivo de Pedro',
            'clinic_workspace_id' => $pedroClinic->id,
        ]);
        $this->post(route('logout'));

        $this->post(route('register'), array_replace($form, [
            'name' => 'Juan',
            'last_name' => 'Vega',
            'clinic_name' => 'Consultorio de Juan',
            'email' => 'juan@example.test',
        ]))->assertRedirect(route('clinic.plan'));
        $this->post(route('clinic.plan.start'), ['plan' => 'consultorio'])->assertRedirect(route('inicio'));
        $this->get(route('inicio'))->assertOk()->assertSee('Consultorio de Juan')->assertDontSee('Consultorio de Pedro');
        $this->get(route('pacientes'))->assertOk()->assertDontSee('Paciente Exclusivo de Pedro');
        $this->get(route('admin.users'))->assertOk()->assertSee('Invitar gente');
        $this->post(route('logout'));

        $this->post('/login', ['email' => 'pedro@example.test', 'password' => $password])
            ->assertRedirect(route('inicio'))->assertSessionHas('clinic_workspace_id', $pedroClinic->id);
        $this->get(route('pacientes'))->assertOk()->assertSee('Paciente Exclusivo de Pedro');
    }

    public function test_doctors_and_assistants_can_see_only_their_own_workspace_assistants(): void
    {
        $owner = User::factory()->create();
        $workspace = ClinicWorkspace::createForOwner($owner, 'Consultorio local');
        $otherWorkspace = ClinicWorkspace::createForOwner(User::factory()->create(), 'Otro consultorio');
        $doctor = User::factory()->create(['role' => 'doctor']);
        $assistant = User::factory()->create(['name' => 'Asistente del mismo equipo', 'role' => 'asistente']);
        $otherAssistant = User::factory()->create(['name' => 'Asistente del equipo externo', 'role' => 'asistente']);
        $workspace->members()->attach($doctor, ['role' => 'doctor']);
        $workspace->members()->attach($assistant, ['role' => 'asistente']);
        $otherWorkspace->members()->attach($otherAssistant, ['role' => 'asistente']);

        foreach ([$doctor, $assistant] as $member) {
            $this->actingAs($member)->withSession(['clinic_workspace_id' => $workspace->id])
                ->get(route('calendario'))
                ->assertOk()
                ->assertSee('Asistentes del consultorio')
                ->assertSee($assistant->name)
                ->assertDontSee($otherAssistant->name)
                ->assertDontSee('name="target"', false);
        }
    }

    public function test_clinical_routes_reject_records_from_other_workspaces(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $clinicA = ClinicWorkspace::createForOwner($ownerA, 'Consultorio A');
        $clinicB = ClinicWorkspace::createForOwner($ownerB, 'Consultorio B');

        $context = app(ClinicContext::class);
        $records = [];
        foreach ([$clinicA, $clinicB] as $clinic) {
            $context->workspaceId = $clinic->id;
            $patient = Patient::create([
                'nombre' => 'Paciente '.$clinic->id,
                'telefono' => '2225551234',
                'email' => 'paciente@example.test',
            ]);
            $dentist = Dentist::create(['nombre' => 'Dentista '.$clinic->id, 'especialidad' => 'General', 'telefono' => '2225554321']);
            $appointment = Appointment::create([
                'patient_id' => $patient->id,
                'dentist_id' => $dentist->id,
                'date' => today()->addWeek(),
                'time' => '10:00',
                'tipo' => 'Consulta',
            ]);
            $records[] = [$patient, $appointment, $dentist];
        }
        $context->workspaceId = null;

        [[$patientA, $appointmentA], [$patientB, $appointmentB, $dentistB]] = $records;
        $this->actingAs($ownerA)->withSession(['clinic_workspace_id' => $clinicA->id]);
        $this->get(route('pacientes'))->assertOk()
            ->assertSee($patientA->nombre)->assertDontSee($patientB->nombre);
        $this->get(route('citas.show', $appointmentA->id))->assertOk();
        $this->get(route('citas.show', $appointmentB->id))->assertNotFound();

        Pdf::shouldReceive('loadView')->once()->withArgs(
            fn ($view, $data) => $view === 'reportes.pdf'
                && $data['rows']->pluck('paciente')->all() === [$patientA->nombre]
        )->andReturnSelf();
        Pdf::shouldReceive('setPaper')->once()->with('letter', 'landscape')->andReturnSelf();
        Pdf::shouldReceive('download')->once()->andReturn(response('PDF de prueba'));
        $this->get(route('reportes.export', [
            'reporte' => 'citas',
            'periodo' => 'Personalizado',
            'desde' => today()->toDateString(),
            'hasta' => today()->addWeeks(2)->toDateString(),
        ]))->assertOk();

        $this->put(route('pacientes.medical.update', $patientB->id), [])->assertNotFound();
        $this->postJson(route('pacientes.clinical.store', $patientA->id), [
            'dentist_id' => $dentistB->id,
            'recorded_at' => now()->toDateTimeString(),
            'reason' => 'Consulta de prueba',
        ])->assertUnprocessable()->assertJsonValidationErrors('dentist_id');
        $this->withSession(['clinic_workspace_id' => $clinicB->id])->get(route('pacientes'))->assertForbidden();
        $this->app['session']->forget('clinic_workspace_id');
        $this->get(route('pacientes'))->assertForbidden();

        $this->actingAs($ownerB)->withSession(['clinic_workspace_id' => $clinicB->id]);
        $this->get(route('citas.show', $appointmentA->id))->assertNotFound();
        $this->get(route('citas.show', $appointmentB->id))->assertOk();
    }

    public function test_workspaces_keep_their_members_and_roles_separate(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $dentist = User::factory()->create();
        $assistant = User::factory()->create();

        $first = ClinicWorkspace::createForOwner($firstOwner, 'Consultorio de Pedrito');
        $second = ClinicWorkspace::createForOwner($secondOwner, 'Consultorio de Juan');
        $this->assertTrue($first->owner->is($firstOwner));
        $this->assertTrue($second->owner->is($secondOwner));
        $first->members()->attach($dentist, ['role' => 'doctor']);
        $second->members()->attach($dentist, ['role' => 'asistente']);
        $second->members()->attach($assistant, ['role' => 'asistente']);

        $this->assertSame([$first->id], $firstOwner->clinicWorkspaces()->pluck('clinic_workspaces.id')->all());
        $this->assertSame([$second->id], $secondOwner->clinicWorkspaces()->pluck('clinic_workspaces.id')->all());
        $this->assertFalse($first->members()->whereKey($assistant->id)->exists());
        $this->assertSame('admin', $first->members()->findOrFail($firstOwner->id)->pivot->role);
        $this->assertSame('doctor', $dentist->clinicWorkspaces()->findOrFail($first->id)->pivot->role);
        $this->assertSame('asistente', $dentist->clinicWorkspaces()->findOrFail($second->id)->pivot->role);
    }
}
