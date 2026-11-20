<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_system_management_without_clinical_information(): void
    {
        $this->withSession(['access_role' => 'superadmin'])
            ->get(route('admin.system'))
            ->assertOk()
            ->assertSee('Gesti&oacute;n del sistema', false)
            ->assertSee('Diagn&oacute;stico de servicios', false)
            ->assertSee('Herramientas de mantenimiento')
            ->assertSee('Respaldo de base de datos')
            ->assertDontSee('Pacientes atendidos')
            ->assertDontSee('Tratamientos clínicos');
    }

    public function test_assistant_cannot_access_system_management(): void
    {
        $this->withSession(['access_role' => 'asistente'])
            ->get(route('admin.system'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('warning');
    }

    public function test_doctor_is_prompted_to_switch_account_before_entering_admin_and_can_then_login_as_admin(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor', 'password' => 'Doctor123!']);
        $admin = User::factory()->create(['role' => 'superadmin', 'password' => 'Admin123!']);

        $this->post(route('login'), [
            'email' => $doctor->email,
            'password' => 'Doctor123!',
        ])->assertRedirect(route('inicio'));

        $this->get(route('admin.system'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('warning', 'No tienes permiso para acceder a esta seccion. Inicia sesion con una cuenta autorizada.');

        $this->post(route('login'), [
            'email' => $admin->email,
            'password' => 'Admin123!',
        ])->assertRedirect(route('inicio'))
            ->assertSessionHas('access_role', 'superadmin')
            ->assertSessionHas('access_user_id', $admin->id);

        $this->get(route('admin.system'))
            ->assertOk()
            ->assertViewIs('admin.system');
    }

    public function test_admin_can_clear_cache_and_the_action_is_audited(): void
    {
        $this->withSession(['access_role' => 'superadmin', 'access_name' => 'Admin Cache'])
            ->post(route('admin.system.cache'))
            ->assertRedirect()
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'Se liberaron'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.system.cache_cleared',
            'actor_name' => 'Admin Cache',
        ]);

        $audit = AuditLog::where('action', 'admin.system.cache_cleared')->latest()->firstOrFail();
        $this->assertArrayHasKey('before_bytes', $audit->metadata);
        $this->assertArrayHasKey('after_bytes', $audit->metadata);
        $this->assertArrayHasKey('freed_bytes', $audit->metadata);
    }
}
