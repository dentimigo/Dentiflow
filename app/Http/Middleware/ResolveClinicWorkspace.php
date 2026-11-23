<?php

namespace App\Http\Middleware;

use App\Support\ClinicContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ResolveClinicWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        $workspaceId = $request->session()->get('clinic_workspace_id');
        $routeName = $request->route()?->getName();
        if ($routeName === null || Str::is(['role.select', 'login', 'logout', 'register*', 'password.*', 'marketing.*'], $routeName)) {
            return $next($request);
        }

        $user = $request->user();
        $context = app(ClinicContext::class);
        if ($workspaceId === null) {
            if ($user && $user->clinicWorkspaces()->exists()) {
                abort_unless($user->clinicWorkspaces()->whereKey($context->legacyId())->exists(), 403);
            }

            return $next($request);
        }

        abort_unless(is_int($workspaceId) && $workspaceId > 0, 403);
        $workspace = $user?->clinicWorkspaces()->whereKey($workspaceId)->first();
        abort_unless($workspace, 403);
        abort_unless(in_array($workspace->pivot->role, ['admin', 'doctor', 'asistente'], true), 403);

        $isPlanRoute = Str::is(['clinic.plan', 'clinic.plan.start', 'clinic.plan.portal'], $routeName);
        if (! $isPlanRoute && ! Str::is(['inicio', 'calendario*', 'citas*', 'pacientes*', 'expedientes*', 'reportes*', 'inventario*', 'perfil*', 'admin.users', 'admin.users.invite'], $routeName)) {
            abort(403);
        }

        $context->workspaceId = (int) $workspace->id;
        $context->workspaceName = $workspace->name;
        $context->role = $workspace->pivot->role;
        $request->attributes->set('clinic_workspace', $workspace);

        try {
            if (! $isPlanRoute && ! $workspace->hasWorkspaceAccess()) {
                return $request->expectsJson()
                    ? response()->json([
                        'message' => 'Activa o renueva el plan para agendar citas o hacer cambios.',
                        'renewal_url' => route('clinic.plan'),
                    ], 403)
                    : redirect()->route('clinic.plan');
            }

            return $next($request);
        } finally {
            $context->workspaceId = null;
            $context->workspaceName = null;
            $context->role = null;
        }
    }
}
