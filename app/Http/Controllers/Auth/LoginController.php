<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\DatabaseConnectionRetry;
use App\Support\ClinicContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PDOException;
use Symfony\Component\HttpFoundation\Response;

class LoginController extends Controller
{
    public function store(Request $request, DatabaseConnectionRetry $databaseRetry): RedirectResponse|Response
    {
        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email'))),
        ]);

        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'role' => 'nullable|in:doctor,asistente,admin,superadmin',
        ], [
            'email.required' => 'El correo electronico es obligatorio.',
            'email.email' => 'Ingresa un correo electronico valido.',
            'password.required' => 'La contrasena es obligatoria.',
        ]);

        try {
            $user = $databaseRetry->run(
                fn () => User::where('email', $request->input('email'))->first()
            );
        } catch (PDOException $exception) {
            if (! $databaseRetry->wasConnectionLost($exception)) {
                throw $exception;
            }

            report($exception);

            return response()
                ->view('errors.database-unavailable', status: 503)
                ->header('Retry-After', '10');
        }

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            return back()
                ->withErrors(['email' => 'Las credenciales no coinciden con nuestros registros.'])
                ->withInput($request->only('email'))
                ->with('login_credentials_failed', true);
        }

        $invitationUrl = $request->session()->get('clinic_invitation_url');
        $workspace = null;
        if ($user->role !== 'superadmin') {
            $memberships = $user->clinicWorkspaces();
            $hasLegacyAccess = $user->role !== 'pendiente'
                && (clone $memberships)->whereKey(app(ClinicContext::class)->legacyId())->exists();
            if (! $hasLegacyAccess) {
                $workspace = $memberships->orderByPivot('created_at', 'desc')->orderByDesc('clinic_workspaces.id')->first();
            }
        }

        if ($user->role === 'pendiente' && ! $workspace && ! $invitationUrl) {
            $request->session()->regenerate();

            return redirect()
                ->route('register.pending')
                ->with([
                    'pending_name' => $user->name,
                    'pending_email' => $user->email,
                    'pending_requested_role' => $user->requested_role,
                ]);
        }

        $selectedRole = $request->input('role');
        $role = $workspace?->pivot->role ?? $user->role;
        if ($selectedRole && $role !== $selectedRole) {
            return back()
                ->withErrors(['email' => 'No fue posible iniciar sesion con los datos proporcionados.'])
                ->withInput($request->only('email', 'role'));
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->forget(['clinic_workspace_id', 'clinic_invitation_url']);
        if ($workspace) {
            $request->session()->put('clinic_workspace_id', (int) $workspace->id);
        }

        $request->session()->put([
            'access_role' => $role,
            'access_user_id' => $user->id,
            'access_name' => $user->name,
            'access_email' => $user->email,
            'access_gender' => $user->gender,
        ]);

        // Railway usa sesiones en MySQL. Guardarlas antes de redirigir evita que
        // el siguiente request llegue sin la autenticacion recien creada.
        $request->session()->save();

        return $invitationUrl
            ? redirect()->to($invitationUrl)
            : redirect()->route($workspace && ! $workspace->hasWorkspaceAccess() ? 'clinic.plan' : 'inicio');
    }
}
