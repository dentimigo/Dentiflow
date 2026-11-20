<?php

namespace Tests\Feature;

use App\Models\Dentist;
use App\Models\DentistSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProfileBackendTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_profile_keeps_its_clinical_identity(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);
        Dentist::create([
            'user_id' => $doctor->id,
            'nombre' => 'Dra. Perfil Clínico',
            'telefono' => '2220000099',
        ]);

        $this->withSession([
            'access_role' => 'doctor',
            'access_user_id' => $doctor->id,
            'access_email' => $doctor->email,
            'access_name' => $doctor->name,
        ])->get(route('perfil'))
            ->assertOk()
            ->assertSee('Área clínica')
            ->assertSee('Agenda semanal')
            ->assertDontSee('Panel operativo');
    }

    public function test_doctor_can_update_profile_without_nulling_required_dentist_phone(): void
    {
        $doctor = User::factory()->create([
            'name' => 'Doctor Perfil',
            'email' => 'doctor.perfil@example.test',
            'role' => 'doctor',
        ]);
        $dentist = Dentist::create([
            'user_id' => $doctor->id,
            'nombre' => 'Doctor Perfil',
            'telefono' => '2220000000',
            'especialidad' => 'General',
        ]);

        $this->withSession([
            'access_role' => 'doctor',
            'access_user_id' => $doctor->id,
            'access_email' => $doctor->email,
            'access_name' => $doctor->name,
        ])->post(route('perfil.update'), [
            'nombre' => 'Doctor Perfil Nuevo',
            'email' => 'doctor.nuevo@example.test',
            'telefono' => '',
            'especialidad' => '',
        ])->assertRedirect(route('perfil'));

        $this->assertDatabaseHas('users', [
            'id' => $doctor->id,
            'name' => 'Doctor Perfil Nuevo',
            'email' => 'doctor.nuevo@example.test',
        ]);
        $this->assertDatabaseHas('dentists', [
            'id' => $dentist->id,
            'nombre' => 'Doctor Perfil Nuevo',
            'telefono' => 'No registrado',
            'especialidad' => 'Odontologia general',
        ]);
    }

    public function test_doctor_profile_name_must_not_duplicate_another_dentist(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);
        Dentist::create(['user_id' => $doctor->id, 'nombre' => 'Dra. Propia', 'telefono' => '2220000000']);
        Dentist::create(['nombre' => 'Dra. Ocupada', 'telefono' => '2220000001']);

        $this->withSession([
            'access_role' => 'doctor',
            'access_user_id' => $doctor->id,
            'access_email' => $doctor->email,
            'access_name' => $doctor->name,
        ])->post(route('perfil.update'), [
            'nombre' => 'Dra. Ocupada',
            'email' => $doctor->email,
            'telefono' => '2220000000',
            'especialidad' => 'General',
        ])->assertSessionHasErrors('nombre');
    }

    public function test_user_can_update_password_with_current_password(): void
    {
        $user = User::factory()->create([
            'role' => 'asistente',
            'password' => 'Password123!',
        ]);

        $session = [
            'access_role' => 'asistente',
            'access_user_id' => $user->id,
            'access_email' => $user->email,
            'access_name' => $user->name,
        ];

        $this->withSession($session)
            ->get(route('perfil.security'))
            ->assertOk()
            ->assertSee('Actualiza tu contrase&ntilde;a directamente desde tu sesi&oacute;n activa.', false)
            ->assertDontSee('Enviar c&oacute;digo', false)
            ->assertDontSee('No recuerdo mi contrase&ntilde;a', false);

        $this->withSession($session)->post(route('perfil.password.update'), [
            'current_password' => 'Password123!',
            'password' => 'NuevaPassword123!',
            'password_confirmation' => 'NuevaPassword123!',
        ])->assertRedirect(route('perfil.security'));

        $this->assertTrue(Hash::check('NuevaPassword123!', $user->fresh()->password));
    }

    public function test_superadmin_can_open_security_from_profile_and_update_password(): void
    {
        $admin = User::where('email', 'admin@gmail.com')->firstOrFail();
        $admin->update(['password' => 'Admin123!']);
        $session = [
            'access_role' => 'superadmin',
            'access_user_id' => $admin->id,
            'access_email' => $admin->email,
            'access_name' => $admin->name,
        ];

        $this->withSession($session)
            ->get(route('perfil'))
            ->assertOk()
            ->assertSee(route('perfil.security'), false);

        $this->withSession($session)
            ->post(route('perfil.password.update'), [
                'current_password' => 'Admin123!',
                'password' => 'NuevaAdmin123!',
                'password_confirmation' => 'NuevaAdmin123!',
            ])
            ->assertRedirect(route('perfil.security'))
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('NuevaAdmin123!', $admin->fresh()->password));
    }

    public function test_user_can_reset_password_with_one_time_email_code(): void
    {
        Mail::fake();
        $user = User::factory()->create([
            'role' => 'doctor',
            'password' => 'Password123!',
        ]);
        $session = [
            'access_role' => 'doctor',
            'access_user_id' => $user->id,
            'access_email' => $user->email,
            'access_name' => $user->name,
        ];

        $response = $this->withSession($session)->post(route('perfil.password.code.send'), [
            'email' => $user->email,
        ])->assertRedirect()
            ->assertSessionHas('code_sent', true);

        $code = $response->getSession()->get('password_otp_test_code');
        $this->assertNotNull($code);

        $this->post(route('perfil.password.code.reset'), [
            'email' => $user->email,
            'code' => $code,
            'password' => 'Recuperada123!',
            'password_confirmation' => 'Recuperada123!',
        ])->assertRedirect(route('perfil.security'))
            ->assertSessionMissing('password_otp_hash');

        $this->assertTrue(Hash::check('Recuperada123!', $user->fresh()->password));

        $this->post(route('perfil.password.code.reset'), [
            'email' => $user->email,
            'code' => $code,
            'password' => 'OtraPassword123!',
            'password_confirmation' => 'OtraPassword123!',
        ])->assertSessionHasErrors('code');
    }

    public function test_doctor_can_update_weekly_schedule(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);
        $dentist = Dentist::create(['user_id' => $doctor->id, 'nombre' => 'Dra. Agenda Perfil', 'telefono' => '2220000000']);
        $schedule = [];

        foreach (range(1, 7) as $weekday) {
            $schedule[$weekday] = [
                'enabled' => $weekday <= 5 ? '1' : '0',
                'start_time' => '08:00',
                'end_time' => $weekday === 5 ? '14:00' : '16:00',
            ];
        }

        $this->withSession([
            'access_role' => 'doctor',
            'access_user_id' => $doctor->id,
            'access_email' => $doctor->email,
            'access_name' => $doctor->name,
        ])->post(route('perfil.schedule.update'), [
            'schedule' => $schedule,
        ])->assertRedirect(route('perfil'));

        $this->assertDatabaseHas('dentist_schedules', [
            'dentist_id' => $dentist->id,
            'weekday' => 1,
            'enabled' => true,
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);
        $this->assertEquals(7, DentistSchedule::where('dentist_id', $dentist->id)->count());
    }
}
