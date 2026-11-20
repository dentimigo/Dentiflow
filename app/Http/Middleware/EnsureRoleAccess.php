<?php

namespace App\Http\Middleware;

use App\Models\ClinicSetting;
use App\Models\User;
use App\Support\ClinicContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureRoleAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        if (!$routeName || Str::is(['role.select', 'login', 'register', 'register.*', 'password.*', 'storage.*', 'logout'], $routeName)) {
            return $next($request);
        }

        $authenticatedUser = $request->user();
        $effectiveRole = app(ClinicContext::class)->role ?? $authenticatedUser?->role;
        if ($authenticatedUser && (
            (int) session('access_user_id') !== (int) $authenticatedUser->id
            || session('access_role') !== $effectiveRole
        )) {
            $request->session()->put([
                'access_role' => $effectiveRole,
                'access_user_id' => $authenticatedUser->id,
                'access_name' => $authenticatedUser->name,
                'access_email' => $authenticatedUser->email,
                'access_gender' => $authenticatedUser->gender,
            ]);
        }

        $role = $effectiveRole ?? session('access_role');

        if (!$role) {
            return redirect()->route('role.select');
        }

        $userId = $request->session()->get('access_user_id');
        if ($userId && ! User::whereKey($userId)->exists()) {
            $request->session()->forget(['access_role', 'access_user_id', 'access_name', 'access_email', 'access_gender']);
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('warning', 'Tu cuenta no esta disponible. Contacta al soporte de la clinica para recuperar el acceso.');
        }

        if ($role === 'pendiente') {
            return redirect()->route('register.pending');
        }

        if ($request->attributes->has('clinic_workspace') && Str::is('clinic.plan*', $routeName)) {
            return $next($request);
        }

        if ($role === 'superadmin' && Str::is('admin.users*', $routeName)) {
            return redirect()->route('inicio')->with('access_denied', 'No tienes permiso para acceder a esta seccion.');
        }

        if ($role !== 'superadmin' && ! ($role === 'admin' && Str::is('admin.users*', $routeName)) && Str::is('admin.*', $routeName)) {
            return redirect()->route('login')->with(
                'warning',
                'No tienes permiso para acceder a esta seccion. Inicia sesion con una cuenta autorizada.'
            );
        }

        if ($this->maintenanceBlocks($role, $routeName)) {
            return redirect()->route('inicio')->with('access_denied', 'Esta seccion no esta disponible temporalmente.');
        }

        $settings = ClinicSetting::current();
        $permissions = [
            'superadmin' => ['inicio', 'admin.*', 'dentistas*', 'perfil*', 'logout'],
            'admin' => ['inicio', 'calendario*', 'citas*', 'pacientes*', 'expedientes*', 'reportes*', 'inventario*', 'admin.users*', 'perfil*', 'logout'],
            'doctor' => ['inicio', 'calendario*', 'citas*', 'pacientes*', 'expedientes*', 'reportes*', 'perfil*', 'logout'],
            'asistente' => $settings->assistantAllowedRoutes(),
        ];

        if (!isset($permissions[$role]) || !Str::is($permissions[$role], $routeName)) {
            return redirect()->route('inicio')->with('access_denied', 'No tienes permiso para acceder a esta seccion.');
        }

        return $next($request);
    }

    private function maintenanceBlocks(string $role, string $routeName): bool
    {
        if ($role === 'superadmin' || ! ClinicSetting::current()->maintenance_mode_enabled) {
            return false;
        }

        // Durante mantenimiento se conserva inicio/perfil/logout para evitar dejar usuarios atrapados.
        return ! Str::is(['inicio', 'perfil*', 'logout'], $routeName);
    }
}

