<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ClinicWorkspace;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class ClinicRegistrationController extends Controller
{
    public function show(Request $request)
    {
        if ($request->user()) {
            return redirect()->route('inicio');
        }

        return view('auth.register', ['creatingClinic' => true]);
    }

    public function store(Request $request)
    {
        if ($request->user()) {
            return redirect()->route('inicio');
        }

        if (is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower($request->input('email'))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:127'],
            'last_name' => ['required', 'string', 'max:127'],
            'clinic_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => [
                'bail', 'required', 'string', 'confirmed', 'min:8',
                fn ($attribute, $value, $fail) => strlen($value) > 72
                    ? $fail('La contraseña supera la longitud admitida.')
                    : null,
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],
            'role' => ['prohibited'],
        ], [
            'name.required' => 'Escribe tu nombre.',
            'last_name.required' => 'Escribe tus apellidos.',
            'clinic_name.required' => 'Escribe el nombre del consultorio.',
            'email.required' => 'Escribe tu correo electrónico.',
            'email.unique' => 'Ya existe una cuenta con ese correo. Puedes iniciar sesión.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'role.prohibited' => 'El rol se asigna automáticamente a tu agenda.',
        ]);

        [$owner, $workspace] = DB::transaction(function () use ($data) {
            $owner = User::create([
                'name' => $data['name'].' '.$data['last_name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => 'pendiente',
            ]);

            return [$owner, ClinicWorkspace::createForOwner($owner, $data['clinic_name'], trialRequired: true)];
        });

        Auth::login($owner);
        $request->session()->regenerate();
        $request->session()->forget('clinic_invitation_url');
        $request->session()->put('clinic_workspace_id', (int) $workspace->id);

        return redirect()->route('clinic.plan');
    }
}
