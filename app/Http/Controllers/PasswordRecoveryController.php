<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;

class PasswordRecoveryController extends Controller
{
    public function showForgotForm(Request $request)
    {
        $email = $request->query('email', $request->session()->get('password_reset_email', ''));
        $codeSent = $request->session()->has('password_reset_hash') || session('code_sent');

        if ($email && ! $codeSent) {
            $user = User::where('email', $email)->first();
            if (! $user) {
                return redirect()->route('login')->withErrors(['email' => 'No encontramos una cuenta con ese correo. Ingresa el correo correcto en el inicio de sesión.']);
            }

            try {
                $this->storeResetCode($request, $user);
            } catch (\Throwable $exception) {
                report($exception);
                $this->forgetResetCode($request);

                return redirect()
                    ->route('password.forgot')
                    ->withErrors(['email' => 'No fue posible enviar el código. Verifica la configuración de correo e inténtalo nuevamente.']);
            }
            $request->session()->flash('success', 'Enviamos un código de un solo uso a tu correo.');
            $codeSent = true;
        }

        return view('auth.forgot-password', [
            'email' => old('email', $email),
            'codeSent' => $codeSent,
        ]);
    }

    private function storeResetCode(Request $request, User $user): void
    {
        $code = (string) random_int(100000, 999999);

        $request->session()->put([
            'password_reset_hash' => Hash::make($code),
            'password_reset_email' => mb_strtolower($user->email),
            'password_reset_expires_at' => now()->addMinutes(10)->timestamp,
            'password_reset_attempts' => 0,
            'password_reset_sent_at' => now()->timestamp,
            'password_reset_verified' => false,
            'code_sent' => true,
        ]);

        if (app()->environment('testing')) {
            $request->session()->put('password_reset_test_code', $code);
        }

        $this->sendResetCodeEmail($user, $code);
    }

    public function sendCode(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
        ], [
            'email.required' => 'Ingresa el correo electrónico de tu cuenta.',
            'email.email' => 'Ingresa un correo electrónico válido.',
        ]);

        $user = User::where('email', $data['email'])->first();
        if (! $user) {
            return back()->withErrors(['email' => 'No encontramos una cuenta registrada con ese correo electrónico.'])->withInput();
        }

        $lastSentAt = (int) $request->session()->get('password_reset_sent_at', 0);
        if ($lastSentAt > now()->subMinute()->timestamp) {
            return back()->withErrors(['email' => 'Espera un minuto antes de solicitar otro código.'])->withInput();
        }

        try {
            $code = (string) random_int(100000, 999999);

            $request->session()->put([
                'password_reset_hash' => Hash::make($code),
                'password_reset_email' => mb_strtolower($user->email),
                'password_reset_expires_at' => now()->addMinutes(10)->timestamp,
                'password_reset_attempts' => 0,
                'password_reset_sent_at' => now()->timestamp,
                'password_reset_verified' => false,
            ]);

            if (app()->environment('testing')) {
                $request->session()->put('password_reset_test_code', $code);
            }

            $this->sendResetCodeEmail($user, $code);
        } catch (\Throwable $exception) {
            report($exception);
            $this->forgetResetCode($request);

            return back()
                ->withErrors(['email' => 'No fue posible enviar el código. Verifica la configuración de correo e inténtalo nuevamente.'])
                ->withInput();
        }

        return back()
            ->with('success', 'Enviamos un código de un solo uso a tu correo.')
            ->with('code_sent', true);
    }

    public function verifyCode(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|digits:6',
        ], [
            'code.required' => 'Ingresa el código recibido.',
            'code.digits' => 'El código debe tener 6 dígitos.',
        ]);

        $hash = $request->session()->get('password_reset_hash');
        $expired = (int) $request->session()->get('password_reset_expires_at', 0) < now()->timestamp;
        $email = $request->session()->get('password_reset_email');

        if (! $hash || $expired || ! $email) {
            $this->forgetResetCode($request);

            return redirect()->route('login')->withErrors(['email' => 'No hay un correo válido para recuperar la contraseña. Ingresa tu correo de nuevo desde la pantalla de login.']);
        }

        if (! Hash::check($data['code'], $hash)) {
            $attempts = (int) $request->session()->get('password_reset_attempts', 0);
            $request->session()->put('password_reset_attempts', $attempts + 1);

            return back()->withErrors(['code' => 'El código no es correcto.']);
        }

        $request->session()->put('password_reset_verified', true);

        return redirect()->route('password.forgot.change.form');
    }

    public function showChangePasswordForm(Request $request)
    {
        if (! $request->session()->get('password_reset_verified', false) || ! $request->session()->has('password_reset_email')) {
            return redirect()->route('password.forgot')->with('warning', 'Verifica primero el código enviado a tu correo.');
        }

        return view('auth.change-password-code');
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'password' => [
                'required',
                'string',
                Password::min(8)->mixedCase()->numbers()->symbols(),
                'confirmed',
            ],
        ], [
            'password.required' => 'Ingresa una nueva contraseña.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.mixed' => 'La contraseña debe incluir mayúsculas y minúsculas.',
            'password.numbers' => 'La contraseña debe incluir al menos un número.',
            'password.symbols' => 'La contraseña debe incluir al menos un símbolo.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        $verified = (bool) $request->session()->get('password_reset_verified', false);
        $email = $request->session()->get('password_reset_email');

        if (! $verified || ! $email) {
            return back()->withErrors(['password' => 'Debes verificar el código antes de establecer una nueva contraseña.']);
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->forgetResetCode($request);

            return back()->withErrors(['password' => 'No encontramos la cuenta asociada al correo validado.']);
        }

        $user->update(['password' => $data['password']]);
        $this->forgetResetCode($request);

        return redirect()->route('login')->with('success', 'Contraseña actualizada correctamente. Ya puedes iniciar sesión.');
    }

    private function forgetResetCode(Request $request): void
    {
        $request->session()->forget([
            'password_reset_hash',
            'password_reset_email',
            'password_reset_expires_at',
            'password_reset_attempts',
            'password_reset_sent_at',
            'password_reset_test_code',
            'code_sent',
        ]);
    }

    private function sendResetCodeEmail(User $user, string $code): void
    {
        $apiKey = (string) config('services.brevo.key');
        $fromEmail = (string) config('services.brevo.from_email');
        $fromName = (string) config('services.brevo.from_name', 'DentiFlow');

        if ($apiKey !== '' && $fromEmail !== '') {
            Http::acceptJson()
                ->withHeaders(['api-key' => $apiKey])
                ->timeout(15)
                ->post('https://api.brevo.com/v3/smtp/email', [
                    'sender' => ['name' => $fromName, 'email' => $fromEmail],
                    'to' => [['email' => $user->email, 'name' => $user->name]],
                    'subject' => 'Código para cambiar tu contraseña - DentiFlow',
                    'textContent' => "Tu código DentiFlow es: {$code}\n\nExpira en 10 minutos y solo puede utilizarse una vez.",
                ])
                ->throw();

            return;
        }

        if (app()->environment('production')) {
            throw new \RuntimeException('La API de Brevo no está configurada para el envío de correos.');
        }

        Mail::raw(
            "Tu código DentiFlow es: {$code}\n\nExpira en 10 minutos y solo puede utilizarse una vez.",
            function ($message) use ($user) {
                $message->to($user->email, $user->name)
                    ->subject('Código para cambiar tu contraseña - DentiFlow');
            }
        );
    }
}
