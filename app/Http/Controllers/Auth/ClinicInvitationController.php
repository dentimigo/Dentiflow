<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Dentist;
use App\Models\ClinicWorkspace;
use App\Models\User;
use App\Support\ClinicContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClinicInvitationController extends Controller
{
    public function show(Request $request, string $invitation)
    {
        $invite = $this->findInvitation($invitation);
        abort_unless(ClinicWorkspace::findOrFail($invite->clinic_workspace_id)->hasWorkspaceAccess(), 403, 'La agenda requiere activación.');

        if ($request->user()) {
            $this->verifyRecipient($request->user(), $invite);

            return view('auth.register', [
                'selectedRole' => $invite->role,
                'invitedEmail' => $request->user()->email,
                'acceptClinicInvitation' => true,
                'clinicName' => $invite->clinic_name,
            ]);
        }

        if ($invite->email !== null && User::where('email', $invite->email)->exists()) {
            $request->session()->put('clinic_invitation_url', $request->getRequestUri());

            return redirect()->route('login')->with('warning', 'Inicia sesión con el correo de la invitación para unirte al consultorio.');
        }

        return view('auth.register', [
            'selectedRole' => $invite->role,
            'invitedEmail' => $invite->email,
            'clinicName' => $invite->clinic_name,
        ]);
    }

    public function store(Request $request, string $invitation)
    {
        $invite = $this->findInvitation($invitation);
        $user = $request->user();
        $data = [];
        if ($user) {
            $this->verifyRecipient($user, $invite);
        } else {
            if ($request->input('invitation_login') === '1') {
                $request->session()->put('clinic_invitation_url', $request->getRequestUri());

                return redirect()->route('login');
            }

            $email = $invite->email ?? $request->input('email');
            if (is_string($email)) {
                $request->merge(['email' => Str::lower(trim($email))]);
            }
            $data = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users,email',
                'password' => 'required|string|min:8|confirmed',
                'gender' => 'required|in:hombre,mujer',
            ]);
        }

        $user = DB::transaction(function () use ($invite, $user, $data) {
            $workspace = ClinicWorkspace::query()->lockForUpdate()->findOrFail($invite->clinic_workspace_id);
            abort_unless($workspace->hasWorkspaceAccess(), 403, 'La agenda requiere activación.');

            $alreadyMember = $user && $workspace->members()->whereKey($user->id)->exists();
            if ($alreadyMember && $invite->email === null) {
                throw ValidationException::withMessages(['invitation' => 'Esta cuenta ya pertenece al consultorio.']);
            }
            $seatLimit = $workspace->seatLimit();
            if (! $alreadyMember && $seatLimit !== null && $workspace->members()->count() >= $seatLimit) {
                throw ValidationException::withMessages(['invitation' => 'El consultorio alcanzó el límite de integrantes de su plan.']);
            }

            $claimed = DB::table('user_invitations')->where('id', $invite->id)->whereNull('used_at')
                ->update(['used_at' => now()]);
            abort_unless($claimed === 1, 410, 'La invitación ya se utilizó.');

            $existingAccount = $user !== null;
            $user ??= User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => 'pendiente',
                'gender' => $data['gender'],
                'password' => $data['password'],
            ]);

            $context = app(ClinicContext::class);
            if ($existingAccount && (int) $invite->clinic_workspace_id !== $context->legacyId()
                && in_array($user->role, ['admin', 'doctor', 'asistente'], true)
                && ! $user->clinicWorkspaces()->exists()) {
                $user->clinicWorkspaces()->attach($context->legacyId(), ['role' => $user->role]);
            }

            $joined = DB::table('clinic_workspace_members')->insertOrIgnore([
                'clinic_workspace_id' => $invite->clinic_workspace_id,
                'user_id' => $user->id,
                'role' => $invite->role,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($joined && $invite->role === 'doctor') {
                $previousWorkspace = $context->workspaceId;
                $context->workspaceId = (int) $invite->clinic_workspace_id;
                try {
                    if (! Dentist::where('user_id', $user->id)->exists()) {
                        $name = Dentist::where('nombre', $user->name)->exists()
                            ? Str::limit($user->name, 230, '').' #'.$user->id
                            : $user->name;
                        Dentist::create([
                            'user_id' => $user->id,
                            'nombre' => $name,
                            'telefono' => $user->telefono ?: 'No registrado',
                            'especialidad' => $user->puesto ?: 'Odontología general',
                        ]);
                    }
                } finally {
                    $context->workspaceId = $previousWorkspace;
                }
            }

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('clinic_invitation_url');
        $request->session()->put('clinic_workspace_id', (int) $invite->clinic_workspace_id);

        return redirect()->route('inicio');
    }

    private function findInvitation(string $id): object
    {
        $invite = DB::table('user_invitations')
            ->join('clinic_workspaces', 'clinic_workspaces.id', '=', 'user_invitations.clinic_workspace_id')
            ->where('user_invitations.id', $id)
            ->whereNull('used_at')
            ->first(['user_invitations.*', 'clinic_workspaces.name as clinic_name']);

        abort_unless($invite, 410, 'La invitación ya se utilizó o no está disponible.');

        return $invite;
    }

    private function verifyRecipient(User $user, object $invite): void
    {
        abort_if($user->role === 'superadmin', 403);
        abort_unless($invite->email === null || Str::lower($user->email) === $invite->email, 403, 'Usa la cuenta con el correo de la invitación.');
    }
}
