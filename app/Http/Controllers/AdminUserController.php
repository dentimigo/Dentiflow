<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ClinicWorkspace;
use App\Support\ClinicContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $search = trim($request->query('search', ''));
        $context = app(ClinicContext::class);
        $workspaceOnly = $context->workspaceId !== null;

        $query = User::with('dentist')->select('users.*');
        if ($workspaceOnly) {
            $query->join('clinic_workspace_members as membership', 'membership.user_id', '=', 'users.id')
                ->where('membership.clinic_workspace_id', $context->id())
                ->whereIn('membership.role', ['doctor', 'asistente'])
                ->addSelect('membership.role as clinic_role');
        } else {
            $query->whereIn('users.role', ['doctor', 'asistente'])
                ->addSelect(['clinic_role' => DB::table('clinic_workspace_members')
                    ->select('role')->whereColumn('user_id', 'users.id')
                    ->where('clinic_workspace_id', $context->legacyId())])
                ->withExists(['clinicWorkspaces as has_other_clinics' => fn ($workspaces) => $workspaces
                    ->where('clinic_workspaces.id', '!=', $context->legacyId())])
                ->where(function ($users) use ($context) {
                    $users->whereDoesntHave('clinicWorkspaces')
                        ->orWhereHas('clinicWorkspaces', fn ($workspaces) => $workspaces->whereKey($context->legacyId()));
                });
        }
        $roleColumn = $workspaceOnly ? 'membership.role' : 'users.role';
        $users = $query
            ->when($search !== '', function ($query) use ($search, $roleColumn) {
                $query->where(function ($subQuery) use ($search, $roleColumn) {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere($roleColumn, 'like', "%{$search}%")
                        ->orWhereHas('dentist', fn ($dentistQuery) => $dentistQuery->where('nombre', 'like', "%{$search}%"));
                });
            })
            ->orderBy('name')
            ->paginate(8)
            ->withQueryString();

        $summary = [
            'total' => User::count(),
            'superadmins' => User::where('role', 'superadmin')->count(),
            'admins' => User::where('role', 'admin')->count(),
            'doctors' => User::where('role', 'doctor')->count(),
            'assistants' => User::where('role', 'asistente')->count(),
            'pending' => User::where('role', 'pendiente')->count(),
        ];

        return view('admin.users.index', compact('users', 'summary', 'search', 'workspaceOnly'));
    }

    public function edit(User $user)
    {
        $this->ensureOperationalUser($user);
        $user->load('dentist');

        return view('admin.users.edit', compact('user'));
    }

    public function invite(Request $request)
    {
        $data = $request->validate([
            'invite_role' => ['required', Rule::in(['doctor', 'asistente'])],
        ], [
            'invite_role.required' => 'Selecciona Dentista o Asistente.',
            'invite_role.in' => 'Selecciona Dentista o Asistente.',
        ]);

        $workspace = ClinicWorkspace::findOrFail(app(ClinicContext::class)->id());
        $seatLimit = $workspace->seatLimit();
        if ($seatLimit !== null && $workspace->members()->count() >= $seatLimit) {
            return back()->withInput()->withErrors(['invite_role' => 'Tu agenda alcanzó el límite de integrantes del plan.']);
        }

        $invitationId = (string) Str::uuid();
        DB::table('user_invitations')->insert([
            'id' => $invitationId,
            'clinic_workspace_id' => $workspace->id,
            'role' => $data['invite_role'],
        ]);
        $url = URL::temporarySignedRoute('register.invitation', now()->addDays(3), [
            'invitation' => $invitationId,
        ]);
        $role = $data['invite_role'] === 'doctor' ? 'Dentista' : 'Asistente';
        $message = "Te invito a {$workspace->name} en DentiFlow como {$role}. Usa este enlace dentro de 3 días: {$url}";

        return redirect()->route('admin.users')->with([
            'invite_link' => $url,
            'invite_whatsapp' => 'https://wa.me/?text='.rawurlencode($message),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->ensureOperationalUser($user);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::in(['pendiente', 'doctor', 'asistente'])],
        ]);

        $user->update([
            ...$data,
            'requested_role' => $data['role'] === 'pendiente'
                ? $user->requested_role
                : null,
        ]);
        if ($user->role === 'pendiente') {
            $user->clinicWorkspaces()->detach(app(ClinicContext::class)->legacyId());
        } else {
            $user->clinicWorkspaces()->updateExistingPivot(app(ClinicContext::class)->legacyId(), ['role' => $user->role]);
        }

        if ($user->role === 'doctor') {
            $user->dentist()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'nombre' => $data['name'],
                    'telefono' => $user->telefono ?: 'No registrado',
                    'especialidad' => $user->puesto ?: 'Odontologia general',
                ]
            );
        } elseif ($user->dentist) {
            $user->dentist->update(['nombre' => $data['name']]);
        }

        return redirect()->route('admin.users')->with('success', 'Usuario actualizado correctamente.');
    }

    public function approve(User $user)
    {
        $this->ensureOperationalUser($user);

        if ($user->role !== 'pendiente') {
            return redirect()
                ->route('admin.users')
                ->withErrors(['user' => 'Esta cuenta ya fue revisada.']);
        }

        if (! in_array($user->requested_role, ['doctor', 'asistente'], true)) {
            return redirect()
                ->route('admin.users.edit', $user)
                ->withErrors(['role' => 'La solicitud no tiene un perfil válido. Selecciona el rol manualmente.']);
        }

        $approvedRole = $user->requested_role;
        $user->update([
            'role' => $approvedRole,
            'requested_role' => null,
        ]);

        if ($approvedRole === 'doctor') {
            $user->dentist()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'nombre' => $user->name,
                    'telefono' => $user->telefono ?: 'No registrado',
                    'especialidad' => $user->puesto ?: 'Odontologia general',
                ]
            );
        }

        $roleLabel = $approvedRole === 'doctor' ? 'Doctor' : 'Asistente';

        return redirect()
            ->route('admin.users')
            ->with('success', $user->name.' fue aprobado como '.$roleLabel.'.');
    }

    public function destroy(Request $request, User $user)
    {
        $this->ensureOperationalUser($user);

        if ((int) $request->session()->get('access_user_id') === $user->id) {
            return redirect()
                ->route('admin.users')
                ->withErrors(['user' => 'No puedes eliminar el usuario con el que tienes la sesion activa.']);
        }

        $user->delete();

        return redirect()->route('admin.users')->with('success', 'Usuario eliminado correctamente. Ya no podra acceder al sistema.');
    }

    private function ensureOperationalUser(User $user): void
    {
        abort_unless(in_array($user->role, ['pendiente', 'doctor', 'asistente'], true), 403);
        abort_if($user->clinicWorkspaces()->where('clinic_workspaces.id', '!=', app(ClinicContext::class)->legacyId())->exists(), 403);
        abort_if($user->role === 'pendiente' && ! $user->requested_role && $user->clinicWorkspaces()->exists(), 403);
    }
}
