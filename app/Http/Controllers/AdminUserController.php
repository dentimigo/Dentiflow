<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $search = trim($request->query('search', ''));

        $users = User::with('dentist')
            ->whereIn('role', ['pendiente', 'doctor', 'asistente'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('role', 'like', "%{$search}%")
                        ->orWhereHas('dentist', fn ($dentistQuery) => $dentistQuery->where('nombre', 'like', "%{$search}%"));
                });
            })
            ->orderBy('name')
            ->paginate(8)
            ->withQueryString();

        $pendingUsers = User::query()
            ->where('role', 'pendiente')
            ->orderBy('created_at')
            ->get();

        $summary = [
            'total' => User::count(),
            'superadmins' => User::where('role', 'superadmin')->count(),
            'admins' => User::where('role', 'admin')->count(),
            'doctors' => User::where('role', 'doctor')->count(),
            'assistants' => User::where('role', 'asistente')->count(),
            'pending' => User::where('role', 'pendiente')->count(),
        ];

        return view('admin.users.index', compact('users', 'pendingUsers', 'summary', 'search'));
    }

    public function edit(User $user)
    {
        $this->ensureOperationalUser($user);
        $user->load('dentist');

        return view('admin.users.edit', compact('user'));
    }

    public function invite(Request $request)
    {
        $email = $request->input('invite_email');
        if (is_string($email)) {
            $request->merge(['invite_email' => Str::lower(trim($email))]);
        }

        $data = $request->validate([
            'invite_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'invite_role' => ['required', Rule::in(['doctor', 'asistente'])],
        ], [
            'invite_email.required' => 'Escribe el correo de la persona que quieres invitar.',
            'invite_email.email' => 'Escribe un correo electrónico válido.',
            'invite_email.unique' => 'Ese correo ya tiene una cuenta. Puedes gestionarla desde Usuarios.',
            'invite_role.required' => 'Selecciona Dentista o Asistente.',
            'invite_role.in' => 'Selecciona Dentista o Asistente.',
        ]);

        $role = $data['invite_role'] === 'doctor' ? 'Dentista' : 'Asistente';
        $invitationId = (string) Str::uuid();
        DB::table('user_invitations')->insert(['id' => $invitationId]);

        $url = URL::temporarySignedRoute('register', now()->addDays(3), [
            'invitation' => $invitationId,
            'invited_email' => $data['invite_email'],
            'role' => $data['invite_role'],
        ]);

        $mailer = config('mail.default');
        $transport = config("mail.mailers.{$mailer}.transport");
        if (in_array($transport, ['log', 'null'], true)) {
            return redirect()->route('admin.users')->with('invite_link', $url);
        }

        try {
            $sent = Mail::raw(
                "Te invitaron a crear tu cuenta en DentiFlow con el rol de {$role}.\n\n"
                ."Completa tu registro aquí:\n{$url}\n\n"
                ."El enlace vence en 3 días y se puede usar para completar un solo registro. El administrador revisará tu solicitud de acceso.",
                fn ($message) => $message->to($data['invite_email'])->subject('Invitación a DentiFlow'),
            );
        } catch (TransportExceptionInterface $exception) {
            report($exception);

            return back()->withInput()->withErrors(['invite_email' => 'El servicio de correo no pudo enviar la invitación. Intenta nuevamente.']);
        }

        if (! $sent) {
            return back()->withInput()->withErrors(['invite_email' => 'No se pudo enviar la invitación.']);
        }

        return redirect()->route('admin.users')->with('success', 'Invitación enviada a '.$data['invite_email'].'.');
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
    }
}
