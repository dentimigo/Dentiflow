<?php

namespace Tests\Feature;

use App\Models\Dentist;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LoginBackendTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_normalizes_email_and_persists_the_authenticated_session(): void
    {
        $user = User::create([
            'name' => 'Doctor Railway',
            'email' => 'doctor.railway@example.test',
            'role' => 'doctor',
            'password' => 'Password123!',
        ]);

        $this->post('/login', [
            'email' => '  DOCTOR.RAILWAY@EXAMPLE.TEST  ',
            'password' => 'Password123!',
        ])
            ->assertRedirect(route('inicio'))
            ->assertSessionHas('access_user_id', $user->id)
            ->assertSessionHas('access_role', 'doctor');

        $this->assertAuthenticatedAs($user);
        $this->get(route('inicio'))->assertOk();
    }

    public function test_users_login_through_one_form_and_session_uses_their_stored_role(): void
    {
        foreach (['admin', 'doctor', 'asistente'] as $role) {
            $user = User::create([
                'name' => ucfirst($role).' Login',
                'email' => "{$role}.login@example.test",
                'role' => $role,
                'password' => 'password',
            ]);

            if ($role === 'doctor') {
                Dentist::create([
                    'user_id' => $user->id,
                    'nombre' => $user->name,
                    'telefono' => 'No registrado',
                ]);
            }

            $this->post('/login', [
                'email' => "{$role}.login@example.test",
                'password' => 'password',
            ])
                ->assertRedirect(route('inicio'))
                ->assertSessionHas('access_role', $role)
                ->assertSessionHas('access_email', "{$role}.login@example.test");

            $this->post(route('logout'));
        }
    }

    public function test_login_page_is_the_main_access_without_role_cards(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Plataforma centralizada de gestión')
            ->assertSee('Crear cuenta')
            ->assertSee(route('register'), false)
            ->assertDontSee(route('login', ['role' => 'doctor']), false)
            ->assertDontSee(route('login', ['role' => 'asistente']), false);
    }

    public function test_create_account_page_offers_doctor_and_assistant_options(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Que tipo de cuenta deseas crear?')
            ->assertSee('Volver al inicio de sesi', false)
            ->assertSee(route('login'), false)
            ->assertSee(route('register', ['role' => 'doctor']), false)
            ->assertSee(route('register', ['role' => 'asistente']), false);
    }

    public function test_register_page_keeps_the_selected_profile_type(): void
    {
        $this->get(route('register', ['role' => 'asistente']))
            ->assertOk()
            ->assertSee('Crear cuenta de Asistente')
            ->assertSee('Enviar solicitud de acceso')
            ->assertSee('name="role" value="asistente"', false)
            ->assertSee('name="gender"', false)
            ->assertSee('Hombre')
            ->assertSee('Mujer')
            ->assertDontSee('Crear cuenta de Administrador');
    }

    public function test_doctor_registration_enters_waiting_room_until_approved(): void
    {
        $this->post(route('register'), [
            'role' => 'doctor',
            'name' => 'Dra. Registro Nuevo',
            'email' => 'registro.doctor@example.test',
            'gender' => 'mujer',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect(route('register.pending'))
            ->assertSessionHas('pending_email', 'registro.doctor@example.test')
            ->assertSessionHas('pending_requested_role', 'doctor');

        $this->get(route('register.pending'))
            ->assertOk()
            ->assertSee('Sala de espera')
            ->assertSee('Acceso solicitado: Doctor')
            ->assertSee('Dra. Registro Nuevo');

        $this->assertDatabaseHas('users', [
            'name' => 'Dra. Registro Nuevo',
            'email' => 'registro.doctor@example.test',
            'role' => 'pendiente',
            'requested_role' => 'doctor',
            'gender' => 'mujer',
        ]);

        $this->assertDatabaseMissing('dentists', ['nombre' => 'Dra. Registro Nuevo']);
    }

    public function test_gender_is_stored_in_session_and_personalizes_doctor_label(): void
    {
        $doctor = User::create([
            'name' => 'Dra. Personalizada',
            'email' => 'doctora.personalizada@example.test',
            'role' => 'doctor',
            'gender' => 'mujer',
            'password' => 'Password123!',
        ]);

        Dentist::create([
            'user_id' => $doctor->id,
            'nombre' => $doctor->name,
            'telefono' => 'No registrado',
        ]);

        $this->post(route('login'), [
            'email' => $doctor->email,
            'password' => 'Password123!',
        ])
            ->assertRedirect(route('inicio'))
            ->assertSessionHas('access_gender', 'mujer');

        $this->get(route('inicio'))
            ->assertOk()
            ->assertSee('Doctora');
    }

    public function test_public_registration_rejects_admin_role(): void
    {
        $this->post(route('register'), [
            'role' => 'admin',
            'name' => 'Usuario Sin Rol Elegible',
            'email' => 'sin.rol@example.test',
            'gender' => 'hombre',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', [
            'email' => 'sin.rol@example.test',
        ]);
    }

    public function test_assistant_registration_enters_waiting_room_until_approved(): void
    {
        $this->post(route('register'), [
            'role' => 'asistente',
            'name' => 'Asistente Nueva',
            'email' => 'registro.asistente@example.test',
            'gender' => 'mujer',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect(route('register.pending'))
            ->assertSessionHas('pending_email', 'registro.asistente@example.test')
            ->assertSessionHas('pending_requested_role', 'asistente');

        $this->get(route('register.pending'))
            ->assertOk()
            ->assertSee('Sala de espera')
            ->assertSee('Acceso solicitado: Asistente')
            ->assertSee('Asistente Nueva');

        $this->assertDatabaseHas('users', [
            'email' => 'registro.asistente@example.test',
            'role' => 'pendiente',
            'requested_role' => 'asistente',
            'gender' => 'mujer',
        ]);
        $this->assertDatabaseMissing('dentists', ['nombre' => 'Asistente Nueva']);
    }

    public function test_login_rejects_an_account_from_another_selected_role(): void
    {
        User::create([
            'name' => 'Doctor Rol',
            'email' => 'doctor.rol@example.test',
            'role' => 'doctor',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'role' => 'asistente',
            'email' => 'doctor.rol@example.test',
            'password' => 'password',
        ])->assertSessionHasErrors(['email' => 'No fue posible iniciar sesion con los datos proporcionados.'])
            ->assertSessionMissing('access_role');
    }

    public function test_pending_user_login_returns_to_waiting_page(): void
    {
        User::create([
            'name' => 'Usuario Pendiente',
            'email' => 'pendiente@example.test',
            'role' => 'pendiente',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => 'pendiente@example.test',
            'password' => 'password',
        ])->assertRedirect(route('register.pending'))
            ->assertSessionHas('pending_email', 'pendiente@example.test');
    }

    public function test_each_assigned_role_opens_the_expected_dashboard(): void
    {
        foreach (['admin' => 'admin.dashboard', 'doctor' => 'dashboard.index', 'asistente' => 'dashboard.index'] as $role => $view) {
            $user = User::create([
                'name' => ucfirst($role).' Dashboard',
                'email' => "{$role}.dashboard@example.test",
                'role' => $role,
                'password' => 'password',
            ]);

            if ($role === 'doctor') {
                Dentist::create([
                    'user_id' => $user->id,
                    'nombre' => $user->name,
                    'telefono' => 'No registrado',
                ]);
            }

            $this->post('/login', [
                'email' => "{$role}.dashboard@example.test",
                'password' => 'password',
            ])->assertRedirect(route('inicio'));

            $this->get(route('inicio'))->assertOk()->assertViewIs($view);

            $this->post(route('logout'));
        }
    }

    public function test_login_rejects_invalid_credentials_without_role_input(): void
    {
        User::create([
            'name' => 'Asistente Login',
            'email' => 'asistente.login@example.test',
            'role' => 'asistente',
            'password' => 'password',
        ]);

        $response = $this->post('/login', [
            'email' => 'asistente.login@example.test',
            'password' => 'incorrecta',
        ]);

        $response
            ->assertSessionHasErrors('email')
            ->assertSessionHas('login_credentials_failed', true);

        $page = $this->get(route('login'));
        $message = 'Las credenciales no coinciden con nuestros registros.';
        $page->assertSee($message);
        $page->assertSee('autocomplete="new-password"', false);
        $page->assertSee("passwordInput.value = '';", false);
        $page->assertDontSee('value="password"', false);
        $this->assertSame(1, substr_count($page->getContent(), $message));
    }

    public function test_assistant_login_starts_a_complete_session(): void
    {
        $assistant = User::create([
            'name' => 'Asistente Sesion',
            'email' => 'asistente.sesion@example.test',
            'role' => 'asistente',
            'password' => 'Password123!',
            'telefono' => '2220000000',
            'puesto' => 'Recepcion',
        ]);

        $this->post(route('login'), [
            'email' => $assistant->email,
            'password' => 'Password123!',
        ])
            ->assertRedirect(route('inicio'))
            ->assertSessionHas('access_role', 'asistente')
            ->assertSessionHas('access_user_id', $assistant->id)
            ->assertSessionHas('access_name', 'Asistente Sesion')
            ->assertSessionHas('access_email', $assistant->email);

        $this->assertAuthenticatedAs($assistant);

        $this->get(route('inicio'))
            ->assertOk()
            ->assertSee('Nueva cita');

        $this->get(route('citas.create'))
            ->assertOk()
            ->assertSee('Agendar nueva cita')
            ->assertSee('Dentista');
    }

    public function test_logout_closes_laravel_and_access_sessions(): void
    {
        $assistant = User::create([
            'name' => 'Asistente Logout',
            'email' => 'asistente.logout@example.test',
            'role' => 'asistente',
            'password' => 'Password123!',
        ]);

        $this->post(route('login'), [
            'email' => $assistant->email,
            'password' => 'Password123!',
        ])->assertRedirect(route('inicio'));

        $this->assertAuthenticatedAs($assistant);

        $this->post(route('logout'))
            ->assertRedirect(route('role.select'))
            ->assertSessionHas('logout_success', 'Sesión cerrada correctamente.')
            ->assertSessionMissing('access_role')
            ->assertSessionMissing('access_user_id');

        $this->assertGuest();
    }

    public function test_user_can_reset_password_from_email_link(): void
    {
        Notification::fake();

        $user = User::create([
            'name' => 'Usuario Recuperacion',
            'email' => 'recuperacion@example.test',
            'role' => 'asistente',
            'password' => 'Password123!',
        ]);

        $this->post(route('password.email'), [
            'email' => 'recuperacion@example.test',
        ])->assertSessionHas('success', 'Enviamos un enlace para cambiar tu contrasena al correo indicado.');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $this->get(route('password.reset', [
                'token' => $notification->token,
                'email' => $user->email,
            ]))
                ->assertOk()
                ->assertSee('Cambiar contrasena')
                ->assertSee('value="'.$user->email.'"', false);

            $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'NuevaPassword123!',
                'password_confirmation' => 'NuevaPassword123!',
            ])->assertRedirect(route('login'))
                ->assertSessionHas('success', 'Tu contrasena fue actualizada. Ya puedes iniciar sesion.');

            $this->assertTrue(Hash::check('NuevaPassword123!', $user->fresh()->password));

            return true;
        });

        $this->post(route('login'), [
            'email' => 'recuperacion@example.test',
            'password' => 'NuevaPassword123!',
        ])->assertRedirect(route('inicio'))
            ->assertSessionHas('access_email', 'recuperacion@example.test');
    }

    public function test_reset_password_rejects_link_that_does_not_match_email(): void
    {
        Notification::fake();

        $user = User::create([
            'name' => 'Usuario Token',
            'email' => 'token@example.test',
            'role' => 'asistente',
            'password' => 'Password123!',
        ]);

        User::create([
            'name' => 'Usuario Diferente',
            'email' => 'diferente@example.test',
            'role' => 'asistente',
            'password' => 'Password123!',
        ]);

        $this->post(route('password.email'), [
            'email' => 'token@example.test',
        ]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) {
            $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => 'diferente@example.test',
                'password' => 'NuevaPassword123!',
                'password_confirmation' => 'NuevaPassword123!',
            ])->assertSessionHasErrors('email');

            return true;
        });
    }

    public function test_registration_shows_translated_validation_error_only_once(): void
    {
        $this->from(route('register', ['role' => 'doctor']))->post(route('register'), [
            'role' => 'doctor',
            'name' => 'Doctor Prueba',
            'email' => 'doctor.corto@example.test',
            'gender' => 'hombre',
            'password' => 'corta',
            'password_confirmation' => 'corta',
        ])->assertSessionHasErrors('password');

        $page = $this->get(route('register', ['role' => 'doctor']));
        $message = 'La contrasena debe tener al menos 8 caracteres.';
        $page->assertSee($message);
        $page->assertDontSee('validation.min.string');
        $this->assertSame(1, substr_count($page->getContent(), $message));
    }

    public function test_code_password_change_requires_complex_and_matching_passwords(): void
    {
        $user = User::create([
            'name' => 'Usuario Cambio Seguro',
            'email' => 'cambio.seguro@example.test',
            'role' => 'asistente',
            'password' => 'Password123!',
        ]);

        $session = [
            'password_reset_verified' => true,
            'password_reset_email' => $user->email,
        ];

        $this->withSession($session)
            ->post(route('password.forgot.change'), [
                'password' => 'sololetras',
                'password_confirmation' => 'sololetras',
            ])
            ->assertSessionHasErrors('password');

        $this->withSession($session)
            ->post(route('password.forgot.change'), [
                'password' => 'NuevaPassword123!',
                'password_confirmation' => 'OtraPassword123!',
            ])
            ->assertSessionHasErrors([
                'password' => 'Las contraseñas no coinciden.',
            ]);

        $this->assertTrue(Hash::check('Password123!', $user->fresh()->password));
    }

    public function test_expired_login_form_redirects_back_to_login_with_warning(): void
    {
        Route::post('/test-expired-login', fn () => abort(419));

        $this->post('/test-expired-login')
            ->assertRedirect(route('login'))
            ->assertSessionHas('warning', 'Tu sesion expiro. Recarga el formulario e intenta iniciar sesion de nuevo.');
    }

    public function test_password_recovery_sends_code_through_brevo_api(): void
    {
        config()->set('services.brevo', [
            'key' => 'test-brevo-key',
            'from_email' => 'dentiflow@example.test',
            'from_name' => 'DentiFlow',
        ]);
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => 'test-message'], 201)]);

        $user = User::factory()->create([
            'name' => 'Paciente Recuperacion',
            'email' => 'recuperacion@example.test',
        ]);

        $this->get(route('password.forgot', ['email' => $user->email]))
            ->assertOk()
            ->assertSessionHas('password_reset_email', $user->email);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.brevo.com/v3/smtp/email'
            && $request['to'][0]['email'] === $user->email
            && $request->hasHeader('api-key', 'test-brevo-key'));
    }

    public function test_any_active_profile_can_logout_without_error(): void
    {
        foreach (['admin', 'doctor', 'asistente'] as $role) {
            $this->withSession([
                'access_role' => $role,
                'access_user_id' => 10,
                'access_name' => ucfirst($role),
                'access_email' => "{$role}@dentiflow.com",
            ])->post(route('logout'))
                ->assertRedirect(route('role.select'))
                ->assertSessionMissing('access_role')
                ->assertSessionMissing('access_user_id')
                ->assertSessionMissing('access_name')
                ->assertSessionMissing('access_email');
        }
    }

    public function test_logout_redirects_safely_even_without_active_session(): void
    {
        $this->post(route('logout'))
            ->assertRedirect(route('role.select'));
    }
}
