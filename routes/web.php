<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AdminReportController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\AdminSystemController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\TreatmentController;
use App\Http\Controllers\ClinicalRecordController;
use App\Http\Controllers\PasswordRecoveryController;
use App\Http\Middleware\EnsureRoleAccess;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Dentist;
use Illuminate\Support\Facades\DB;

Route::get('/', function () {
    return view('auth.login');
})->name('role.select');

Route::get('/login', function () {
    return view('auth.login');
})->name('login');

Route::view('/conoce-dentiflow', 'marketing.dentiflow')
    ->withoutMiddleware(EnsureRoleAccess::class)
    ->name('marketing.dentiflow');

Route::post('/login', [LoginController::class, 'store']);

Route::get('/registro-en-espera', function () {
    return view('auth.pending');
})->name('register.pending');

Route::get('/register', function (Request $request) {
    $role = $request->query('role');
    $invitedEmail = $request->query('invited_email');

    if ($invitedEmail !== null) {
        abort_unless($request->hasValidSignature(), 403, 'La invitación venció o el enlace es inválido.');
        abort_unless(DB::table('user_invitations')
            ->where('id', $request->query('invitation'))
            ->whereNull('used_at')
            ->exists(), 410, 'La invitación ya se utilizó o necesitas pedir un enlace nuevo.');
    }

    if ($role === null) {
        return view('auth.role-selector', ['registrationMode' => true]);
    }

    if (! in_array($role, ['doctor', 'asistente'], true)) {
        return redirect()
            ->route('register')
            ->with('warning', 'Elige Doctor o Asistente para crear tu cuenta.');
    }

    return view('auth.register', ['selectedRole' => $role, 'invitedEmail' => $invitedEmail]);
})->name('register');

Route::post('/register', function (Request $request) {
    if ($request->query('invited_email') !== null) {
        abort_unless($request->hasValidSignature(), 403, 'La invitación venció o el enlace es inválido.');

        $request->merge([
            'email' => $request->query('invited_email'),
            'role' => $request->query('role'),
        ]);
    }

    $data = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|max:255|unique:users,email',
        'password' => 'required|string|min:8|confirmed',
        'role' => 'required|in:doctor,asistente',
        'gender' => 'required|in:hombre,mujer',
    ], [
        'name.required' => 'El nombre completo es obligatorio.',
        'name.max' => 'El nombre no puede tener mas de 255 caracteres.',
        'email.required' => 'El correo electronico es obligatorio.',
        'email.email' => 'Ingresa un correo electronico valido.',
        'email.unique' => 'Este correo electronico ya esta registrado.',
        'password.required' => 'La contrasena es obligatoria.',
        'password.min' => 'La contrasena debe tener al menos 8 caracteres.',
        'password.confirmed' => 'Las contrasenas no coinciden.',
        'role.required' => 'Selecciona el tipo de cuenta.',
        'role.in' => 'El tipo de cuenta seleccionado no es valido.',
        'gender.required' => 'Selecciona tu genero.',
        'gender.in' => 'El genero seleccionado no es valido.',
    ]);

    $user = DB::transaction(function () use ($data, $request) {
        if ($request->query('invited_email') !== null) {
            $claimed = DB::table('user_invitations')
                ->where('id', $request->query('invitation'))
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            abort_unless($claimed === 1, 410, 'La invitación ya se utilizó o necesitas pedir un enlace nuevo.');
        }

        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => 'pendiente',
            'requested_role' => $data['role'],
            'gender' => $data['gender'],
            'password' => $data['password'],
        ]);
    });

    return redirect()
        ->route('register.pending')
        ->with([
            'pending_name' => $user->name,
            'pending_email' => $user->email,
            'pending_requested_role' => $user->requested_role,
        ]);
});

Route::get('/forgot-password', [PasswordRecoveryController::class, 'showForgotForm'])->name('password.forgot');
Route::post('/forgot-password/send-code', [PasswordRecoveryController::class, 'sendCode'])->name('password.forgot.send');
Route::post('/forgot-password/verify-code', [PasswordRecoveryController::class, 'verifyCode'])->name('password.forgot.verify');
Route::get('/forgot-password/change', [PasswordRecoveryController::class, 'showChangePasswordForm'])->name('password.forgot.change.form');
Route::post('/forgot-password/change', [PasswordRecoveryController::class, 'changePassword'])->name('password.forgot.change');

Route::post('/forgot-password', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
    ], [
        'email.required' => 'Ingresa el correo electronico de tu cuenta.',
        'email.email' => 'Ingresa un correo electronico valido.',
    ]);

    // Laravel usa password_reset_tokens para generar y validar el token de recuperacion.
    $status = Password::sendResetLink($request->only('email'));

    return $status === Password::RESET_LINK_SENT
        ? back()->with('success', 'Enviamos un enlace para cambiar tu contrasena al correo indicado.')
        : back()->withErrors(['email' => 'No encontramos una cuenta registrada con ese correo electronico.']);
})->name('password.email');

Route::get('/reset-password/{token}', function (string $token, Request $request) {
    return view('auth.reset-password', [
        'token' => $token,
        'email' => $request->query('email'),
    ]);
})->name('password.reset');

Route::post('/reset-password', function (Request $request) {
    $request->validate([
        'token' => 'required',
        'email' => 'required|email',
        'password' => 'required|string|min:8|confirmed',
    ], [
        'email.required' => 'Ingresa el correo electronico de tu cuenta.',
        'email.email' => 'Ingresa un correo electronico valido.',
        'password.required' => 'Ingresa una nueva contrasena.',
        'password.min' => 'La contrasena debe tener al menos 8 caracteres.',
        'password.confirmed' => 'Las contrasenas no coinciden.',
    ]);

    $status = Password::reset(
        $request->only('email', 'password', 'password_confirmation', 'token'),
        function (User $user, string $password) {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        }
    );

    return $status === Password::PASSWORD_RESET
        ? redirect()->route('login')->with('success', 'Tu contrasena fue actualizada. Ya puedes iniciar sesion.')
        : back()
            ->withErrors(['email' => 'El enlace no es valido, expiro o no corresponde a ese correo. Solicita uno nuevo.'])
            ->withInput($request->only('email'));
})->name('password.update');

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->forget(['access_role', 'access_user_id', 'access_name', 'access_email', 'access_gender']);
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()
        ->route('role.select')
        ->with('logout_success', 'Sesión cerrada correctamente.');
})->name('logout');

Route::get('/administracion/configuracion', [AdminSettingsController::class, 'edit'])->name('admin.settings');

Route::post('/administracion/configuracion', [AdminSettingsController::class, 'update'])->name('admin.settings.update');
Route::get('/administracion/preferencias', [AdminSettingsController::class, 'edit'])->name('admin.preferences');
Route::post('/administracion/preferencias', [AdminSettingsController::class, 'update'])->name('admin.preferences.update');
Route::get('/administracion/sistema', [AdminSystemController::class, 'index'])->name('admin.system');
Route::post('/administracion/sistema/cache', [AdminSystemController::class, 'clearCache'])->name('admin.system.cache');
Route::get('/administracion/sistema/respaldo', [AdminSystemController::class, 'downloadBackup'])->name('admin.system.backup');

Route::get('/administracion/calendario', [DashboardController::class, 'adminCalendar'])->name('admin.calendar');
Route::get('/administracion/reportes', [AdminReportController::class, 'index'])->name('admin.reports');
Route::get('/administracion/reportes/citas/{appointment}/editar', [AdminReportController::class, 'editAppointment'])->name('admin.reports.appointments.edit');
Route::put('/administracion/reportes/citas/{appointment}', [AdminReportController::class, 'updateAppointment'])->name('admin.reports.appointments.update');
Route::delete('/administracion/reportes/{treatment}', [AdminReportController::class, 'destroy'])->name('admin.reports.destroy');
Route::get('/administracion/usuarios', [AdminUserController::class, 'index'])->name('admin.users');
Route::post('/administracion/usuarios/invitar', [AdminUserController::class, 'invite'])
    ->middleware('throttle:5,1')
    ->name('admin.users.invite');
Route::get('/administracion/usuarios/{user}/editar', [AdminUserController::class, 'edit'])->name('admin.users.edit');
Route::put('/administracion/usuarios/{user}/aprobar', [AdminUserController::class, 'approve'])->name('admin.users.approve');
Route::put('/administracion/usuarios/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
Route::delete('/administracion/usuarios/{user}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');

Route::redirect('/dashboard', '/inicio');
Route::get('/inicio', [DashboardController::class, 'index'])->name('inicio');
Route::get('/calendario', [DashboardController::class, 'calendar'])->name('calendario');
Route::post('/calendario/bloqueos', [DashboardController::class, 'storeCalendarBlock'])->name('calendario.bloqueos.store');
Route::delete('/calendario/bloqueos/{block}', [DashboardController::class, 'destroyCalendarBlock'])->name('calendario.bloqueos.destroy');

Route::get('/pacientes', [PatientController::class, 'index'])->name('pacientes');
Route::delete('/pacientes/{paciente}', [PatientController::class, 'destroy'])->name('pacientes.destroy')->where('paciente', '[^/]+');
Route::get('/expedientes', [PatientController::class, 'records'])->name('expedientes');
Route::get('/pacientes/crear', [PatientController::class, 'create'])->name('pacientes.create');
Route::post('/pacientes', [PatientController::class, 'store'])->name('pacientes.store');
Route::get('/pacientes/{paciente}/editar', [PatientController::class, 'edit'])->name('pacientes.edit')->where('paciente', '[^/]+');
Route::put('/pacientes/{paciente}', [PatientController::class, 'update'])->name('pacientes.update')->where('paciente', '[^/]+');
Route::get('/pacientes/{paciente}/eliminar', [PatientController::class, 'destroy'])->name('pacientes.delete')->where('paciente', '[^/]+');
Route::get('/pacientes/{paciente}/cancelar', [PatientController::class, 'cancel'])->name('pacientes.cancel')->where('paciente', '[^/]+');
Route::get('/pacientes/{paciente}/expediente', [PatientController::class, 'show'])->name('pacientes.expediente');
Route::post('/pacientes/{patient}/expediente/consultas', [ClinicalRecordController::class, 'store'])->name('pacientes.clinical.store');
Route::put('/pacientes/{patient}/expediente/antecedentes', [ClinicalRecordController::class, 'updateMedicalHistory'])->name('pacientes.medical.update');
Route::get('/pacientes/historial', [PatientController::class, 'history'])->name('pacientes.historial');
Route::delete('/pacientes/historial/{treatment}', [PatientController::class, 'destroyHistoryRecord'])->name('pacientes.historial.destroy');

Route::get('/pacientes/tratamiento', [TreatmentController::class, 'create'])->name('pacientes.tratamiento');
Route::post('/pacientes/tratamientos', [TreatmentController::class, 'store'])->name('pacientes.tratamientos.store');
Route::get('/pacientes/tratamientos', [TreatmentController::class, 'index'])->name('pacientes.tratamientos');
Route::get('/pacientes/tratamientos/{id}/editar', [TreatmentController::class, 'edit'])->name('pacientes.tratamientos.edit');
Route::put('/pacientes/tratamientos/{id}', [TreatmentController::class, 'update'])->name('pacientes.tratamientos.update');
Route::get('/pacientes/tratamientos/{id}/eliminar', [TreatmentController::class, 'destroy'])->name('pacientes.tratamientos.delete');

// Dentistas
use App\Http\Controllers\DentistController;

Route::get('/dentistas', [DentistController::class, 'index'])->name('dentistas');
Route::get('/dentistas/crear', [DentistController::class, 'create'])->name('dentistas.create');
Route::post('/dentistas', [DentistController::class, 'store'])->name('dentistas.store');
Route::get('/dentistas/{id}/editar', [DentistController::class, 'edit'])->name('dentistas.edit');
Route::put('/dentistas/{id}', [DentistController::class, 'update'])->name('dentistas.update');
Route::get('/dentistas/{id}/eliminar', [DentistController::class, 'destroy'])->name('dentistas.delete');

Route::get('/inventario', [InventoryController::class, 'index'])->name('inventario');
Route::get('/inventario/exportar/pdf', [InventoryController::class, 'exportPdf'])->name('inventario.export.pdf');
Route::get('/inventario/exportar/excel', [InventoryController::class, 'exportExcel'])->name('inventario.export.excel');
Route::get('/inventario/crear', [InventoryController::class, 'create'])->name('inventario.create');
Route::post('/inventario', [InventoryController::class, 'store'])->name('inventario.store');
Route::get('/inventario/{id}/editar', [InventoryController::class, 'edit'])->name('inventario.edit');
Route::post('/inventario/{id}', [InventoryController::class, 'update'])->name('inventario.update');
Route::get('/inventario/{id}/eliminar', [InventoryController::class, 'destroy'])->name('inventario.delete');

Route::get('/citas', [AppointmentController::class, 'index'])->name('citas');
Route::get('/citas/crear', [AppointmentController::class, 'create'])->name('citas.create');
Route::post('/citas', [AppointmentController::class, 'store'])->name('citas.store');
Route::get('/citas/historial', [AppointmentController::class, 'history'])->name('citas.historial');
Route::post('/citas/{id}/restaurar', [AppointmentController::class, 'restore'])->name('citas.restaurar');
Route::get('/citas/{id}/editar', [AppointmentController::class, 'edit'])->name('citas.edit');
Route::post('/citas/{id}', [AppointmentController::class, 'update'])->name('citas.update');
Route::get('/citas/{id}/confirmar', [AppointmentController::class, 'confirm'])->name('citas.confirmar');
Route::get('/citas/{id}/eliminar', [AppointmentController::class, 'destroy'])->name('citas.delete');
Route::post('/citas/{id}/cancelar', [AppointmentController::class, 'cancel'])->name('citas.cancelar');
Route::get('/citas/{id}/asistida', [AppointmentController::class, 'attended'])->name('citas.asistida');
Route::post('/citas/{id}/terminar', [AppointmentController::class, 'finish'])->name('citas.terminar');
Route::match(['get', 'post'], '/citas/{id}/recordatorio', [AppointmentController::class, 'markReminder'])->name('citas.recordatorio');
Route::get('/citas/{id}/reprogramar', [AppointmentController::class, 'reprogram'])->name('citas.reprogramar');
Route::post('/citas/{id}/calendario/mover', [AppointmentController::class, 'moveFromCalendar'])->name('citas.calendar.move');
Route::get('/citas/{id}/detalle', [AppointmentController::class, 'show'])->name('citas.show');

Route::get('/reportes', [\App\Http\Controllers\ReportController::class, 'index'])->name('reportes');

Route::get('/reportes/export', [\App\Http\Controllers\ReportController::class, 'export'])->name('reportes.export');
Route::get('/reportes/export/excel', [\App\Http\Controllers\ReportController::class, 'exportExcel'])->name('reportes.export.excel');

Route::get('/perfil', [\App\Http\Controllers\ProfileController::class, 'show'])->name('perfil');
Route::get('/perfil/editar', [\App\Http\Controllers\ProfileController::class, 'edit'])->name('perfil.edit');
Route::get('/perfil/seguridad', [\App\Http\Controllers\ProfileController::class, 'security'])->name('perfil.security');
Route::post('/perfil', [\App\Http\Controllers\ProfileController::class, 'update'])->name('perfil.update');
Route::post('/perfil/password', [\App\Http\Controllers\ProfileController::class, 'updatePassword'])->name('perfil.password.update');
Route::post('/perfil/seguridad/codigo', [\App\Http\Controllers\ProfileController::class, 'sendPasswordCode'])->name('perfil.password.code.send');
Route::post('/perfil/seguridad/codigo/verificar', [\App\Http\Controllers\ProfileController::class, 'resetPasswordWithCode'])->name('perfil.password.code.reset');
Route::post('/perfil/agenda', [\App\Http\Controllers\ProfileController::class, 'updateSchedule'])->name('perfil.schedule.update');
