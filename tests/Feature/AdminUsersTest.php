<?php

namespace Tests\Feature;

use App\Models\Dentist;
use App\Models\ClinicWorkspace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_invite_a_dentist_with_a_single_use_registration_link(): void
    {
        $this->withSession(['access_role' => 'admin'])
            ->post(route('admin.users.invite'), [
                'invite_role' => 'doctor',
            ])->assertRedirect(route('admin.users'))->assertSessionHas('invite_link');

        $url = session('invite_link');
        $whatsapp = session('invite_whatsapp');
        $this->assertStringStartsWith('https://wa.me/?text=', $whatsapp);
        $this->assertStringContainsString($url, rawurldecode($whatsapp));

        $this->flushSession();
        $this->get($url)->assertOk()->assertSee('Ya tengo cuenta');
        $this->get($url.'&role=asistente')->assertForbidden();
        $registration = [
            'name' => 'Dentista Invitado',
            'email' => 'DENTISTA@example.test',
            'role' => 'asistente',
            'gender' => 'hombre',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $this->from($url)->post($url, array_replace($registration, ['name' => '']))
            ->assertRedirect($url)
            ->assertSessionHasErrors('name');
        $this->get($url)->assertOk();
        $this->post($url, $registration)->assertRedirect(route('inicio'));

        $dentist = User::where('email', 'dentista@example.test')->firstOrFail();
        $this->assertDatabaseHas('clinic_workspace_members', [
            'clinic_workspace_id' => ClinicWorkspace::oldest('id')->firstOrFail()->id,
            'user_id' => $dentist->id,
            'role' => 'doctor',
        ]);

        $this->get($url)->assertStatus(410);
        auth()->logout();
        $this->flushSession();
        $this->post('/login', ['email' => $dentist->email, 'password' => 'Password123!'])
            ->assertRedirect(route('inicio'))
            ->assertSessionHas('access_role', 'doctor');

        $dentist->delete();
        $this->post($url, $registration)->assertStatus(410);
        $this->assertDatabaseMissing('users', ['email' => 'dentista@example.test']);
    }

    public function test_existing_user_accepts_only_the_inviting_workspace(): void
    {
        config(['mail.default' => 'log']);
        $owner = User::factory()->create();
        $clinic = ClinicWorkspace::createForOwner($owner, 'Consultorio Pedrito');
        $otherClinic = ClinicWorkspace::createForOwner(User::factory()->create(), 'Consultorio Juan');
        $recipient = User::factory()->create(['email' => 'asistente@example.test', 'role' => 'doctor']);
        $otherClinic->members()->attach($recipient, ['role' => 'doctor']);

        $this->actingAs($owner)->withSession(['clinic_workspace_id' => $clinic->id]);
        $this->get(route('admin.users'))->assertOk()->assertDontSee($recipient->email);
        $this->post(route('admin.users.invite'), [
            'invite_role' => 'asistente',
        ])->assertRedirect(route('admin.users'))->assertSessionHas('invite_link');
        $url = session('invite_link');
        $this->get($url)->assertOk()
            ->assertSee('Esta cuenta ya pertenece al consultorio.')
            ->assertSee('Cerrar sesión y crear otra cuenta')
            ->assertDontSee('Aceptar con esta cuenta');
        $this->post($url)->assertSessionHasErrors('invitation');

        $this->post($url, ['invitation_switch_account' => '1'])->assertRedirect($url);
        $this->assertGuest();
        $this->get($url)->assertOk()->assertSee('Crear cuenta y aceptar');
        $this->post($url, ['invitation_login' => '1'])->assertRedirect(route('login'));
        $this->post('/login', ['email' => $recipient->email, 'password' => 'password'])->assertRedirect($url);
        $this->get($url)->assertOk()->assertSee('Consultorio Pedrito');
        $this->post($url)->assertRedirect(route('inicio'))->assertSessionHas('clinic_workspace_id', $clinic->id);
        $this->get(route('calendario'))->assertOk()->assertSessionHas('access_role', 'asistente');
        $this->get($url)->assertStatus(410);
        $this->assertSame('asistente', $recipient->clinicWorkspaces()->findOrFail($clinic->id)->pivot->role);
        $this->assertSame('doctor', $recipient->clinicWorkspaces()->findOrFail($otherClinic->id)->pivot->role);
        $this->actingAs($owner)->withSession(['clinic_workspace_id' => $clinic->id])
            ->get(route('admin.users'))->assertOk()->assertSee($recipient->email);
    }

    public function test_admin_can_copy_a_whatsapp_invitation(): void
    {
        config(['mail.default' => 'log']);

        $this->withSession(['access_role' => 'admin'])
            ->post(route('admin.users.invite'), [
                'invite_role' => 'asistente',
            ])->assertRedirect(route('admin.users'))->assertSessionHas('invite_link');

        $this->get(route('admin.users'))->assertOk()
            ->assertSee('Copia y comparte este enlace')->assertSee('Compartir por WhatsApp')
            ->assertDontSee('invite_phone')
            ->assertDontSee('Solicitudes pendientes');
    }

    public function test_admin_can_open_registered_users_section_without_create_form(): void
    {
        User::factory()->create(['name' => 'Usuario Registrado', 'email' => 'usuario@example.test', 'role' => 'doctor']);

        $this->withSession(['access_role' => 'admin'])
            ->get(route('admin.users'))
            ->assertOk()
            ->assertSee('Usuarios del sistema')
            ->assertSee('Usuario Registrado')
            ->assertDontSee('Dashboard clinico')
            ->assertDontSee('Asignar rol')
            ->assertDontSee('Perfil clínico')
            ->assertDontSee('Crear usuario')
            ->assertDontSee('admin.users.store');
    }

    public function test_admin_cannot_create_users_from_users_section(): void
    {
        $this->withSession(['access_role' => 'admin'])
            ->post('/administracion/usuarios', [
                'name' => 'Usuario Nuevo',
                'email' => 'nuevo@example.test',
                'role' => 'doctor',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])
            ->assertStatus(405);

        $this->assertDatabaseMissing('users', ['email' => 'nuevo@example.test']);
    }

    public function test_admin_can_update_registered_user_basic_data(): void
    {
        $user = User::factory()->create(['name' => 'Nombre Anterior', 'email' => 'anterior@example.test', 'role' => 'doctor']);

        $this->withSession(['access_role' => 'admin'])
            ->put(route('admin.users.update', $user), [
                'name' => 'Nombre Actualizado',
                'email' => 'actualizado@example.test',
                'role' => 'doctor',
            ])
            ->assertRedirect(route('admin.users'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nombre Actualizado',
            'email' => 'actualizado@example.test',
            'role' => 'doctor',
        ]);
    }

    public function test_admin_can_approve_pending_user_with_role(): void
    {
        $user = User::factory()->create([
            'name' => 'Pendiente Aprobacion',
            'email' => 'pendiente.admin@example.test',
            'role' => 'pendiente',
        ]);

        $this->withSession(['access_role' => 'admin'])
            ->put(route('admin.users.update', $user), [
                'name' => 'Dra. Aprobada',
                'email' => 'aprobada@example.test',
                'role' => 'doctor',
            ])
            ->assertRedirect(route('admin.users'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Dra. Aprobada',
            'email' => 'aprobada@example.test',
            'role' => 'doctor',
        ]);

        $this->assertDatabaseHas('dentists', [
            'user_id' => $user->id,
            'nombre' => 'Dra. Aprobada',
            'especialidad' => 'Odontologia general',
        ]);
    }

    public function test_legacy_requests_are_hidden_from_the_clinic_roster(): void
    {
        $user = User::factory()->create([
            'name' => 'Solicitud Doctora',
            'email' => 'solicitud.doctora@example.test',
            'role' => 'pendiente',
            'requested_role' => 'doctor',
        ]);

        $this->withSession(['access_role' => 'admin'])
            ->get(route('admin.users'))
            ->assertOk()
            ->assertDontSee('Solicitudes pendientes')
            ->assertDontSee('Solicitud Doctora');

        $this->put(route('admin.users.approve', $user))
            ->assertRedirect(route('admin.users'))
            ->assertSessionHas('success', 'Solicitud Doctora fue aprobado como Doctor.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'doctor',
            'requested_role' => null,
        ]);
        $this->assertDatabaseHas('dentists', [
            'user_id' => $user->id,
            'nombre' => 'Solicitud Doctora',
        ]);
    }

    public function test_direct_approval_rejects_pending_user_without_requested_profile(): void
    {
        $user = User::factory()->create([
            'role' => 'pendiente',
            'requested_role' => null,
        ]);

        $this->withSession(['access_role' => 'admin'])
            ->put(route('admin.users.approve', $user))
            ->assertRedirect(route('admin.users.edit', $user))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'pendiente',
        ]);
    }

    public function test_admin_can_assign_assistant_role_from_users_section(): void
    {
        $user = User::factory()->create([
            'name' => 'Usuario Recepcion',
            'email' => 'recepcion@example.test',
            'role' => 'pendiente',
        ]);

        $this->withSession(['access_role' => 'admin'])
            ->put(route('admin.users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'asistente',
            ])
            ->assertRedirect(route('admin.users'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'asistente',
        ]);

        $this->assertDatabaseMissing('dentists', [
            'user_id' => $user->id,
        ]);
    }

    public function test_admin_can_delete_user_and_removed_user_cannot_login(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create([
            'name' => 'Usuario Eliminable',
            'email' => 'eliminable@example.test',
            'role' => 'doctor',
            'password' => 'password',
        ]);

        Dentist::create([
            'user_id' => $user->id,
            'nombre' => $user->name,
            'telefono' => 'No registrado',
        ]);

        $this->withSession(['access_role' => 'admin', 'access_user_id' => $admin->id])
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect(route('admin.users'))
            ->assertSessionHas('success', 'Usuario eliminado correctamente. Ya no podra acceder al sistema.');

        $this->assertDatabaseMissing('users', ['email' => 'eliminable@example.test']);
        $this->assertDatabaseHas('dentists', [
            'nombre' => 'Usuario Eliminable',
            'user_id' => null,
        ]);

        $this->post('/login', [
            'email' => 'eliminable@example.test',
            'password' => 'password',
        ])->assertSessionHasErrors('email');
    }

    public function test_deleted_user_active_session_is_rejected_from_protected_sections(): void
    {
        $user = User::factory()->create([
            'email' => 'sesion.eliminada@example.test',
            'role' => 'asistente',
        ]);

        $user->delete();

        $this->withSession([
            'access_role' => 'asistente',
            'access_user_id' => $user->id,
            'access_name' => 'Sesion Eliminada',
            'access_email' => 'sesion.eliminada@example.test',
        ])
            ->get(route('inicio'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('warning', 'Tu cuenta no esta disponible. Contacta al soporte de la clinica para recuperar el acceso.')
            ->assertSessionMissing('access_role');
    }

    public function test_admin_cannot_delete_own_active_user(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin.actual@example.test',
            'role' => 'admin',
        ]);

        $this->withSession(['access_role' => 'admin', 'access_user_id' => $admin->id])
            ->delete(route('admin.users.destroy', $admin))
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'email' => 'admin.actual@example.test',
        ]);
    }
}
