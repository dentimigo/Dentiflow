<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrarse - DentiFlow</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
@php
    $creatingClinic = $creatingClinic ?? false;
    $accountType = [
        'eyebrow' => 'Registro',
    ];
@endphp
<body class="min-h-screen bg-[#fbf0ff] font-['DM_Sans'] text-[#21152e] antialiased">
<main class="min-h-screen lg:grid lg:grid-cols-[minmax(360px,42%)_1fr]">
    <section class="relative overflow-hidden bg-[#512569] px-6 py-8 text-white sm:px-10 lg:min-h-screen lg:px-12 lg:py-10">
        <div class="absolute inset-0 opacity-30" style="background-image: linear-gradient(135deg, rgba(234,210,251,.22) 0 1px, transparent 1px), linear-gradient(45deg, rgba(151,0,207,.18) 0 1px, transparent 1px); background-size: 42px 42px;"></div>
        <div class="absolute inset-x-0 bottom-0 h-56 bg-gradient-to-t from-[#21152e] to-transparent"></div>

        <div class="relative z-10 flex min-h-[520px] flex-col justify-between lg:min-h-[calc(100vh-5rem)]">
            <header class="flex items-center justify-between">
                <a href="{{ route('login') }}" class="flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#9700cf] shadow-lg shadow-[#21152e]/30">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7.3 3.7c1.8-.8 3 .3 4.7.3s2.9-1.1 4.7-.3c2.6 1.2 3 4.8 1.9 7.3-.9 2.1-1.4 3.4-1.6 6.6-.1 1.7-.9 3.4-2.1 3.4-1.7 0-1.2-4.8-2.9-4.8S10.8 21 9.1 21C7.9 21 7.1 19.3 7 17.6c-.2-3.2-.7-4.5-1.6-6.6C4.3 8.5 4.7 4.9 7.3 3.7Z"/></svg>
                    </span>
                    <span>
                        <span class="block text-base font-bold leading-none">DentiFlow</span>
                        <span class="mt-1 block text-[10px] font-bold uppercase tracking-[0.22em] text-white/45">Gestión clínica</span>
                    </span>
                </a>
                <span class="rounded-full border border-white/10 bg-white/10 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-[#ead2fb]">Nueva cuenta</span>
            </header>

            <div class="max-w-xl py-14 lg:py-16">
                <p class="inline-flex rounded-full bg-white/10 px-3 py-1 text-xs font-bold text-[#ead2fb]">{{ $accountType['eyebrow'] }}</p>
                <h1 class="mt-6 max-w-lg text-4xl font-bold leading-tight tracking-tight sm:text-5xl">{{ $creatingClinic ? 'Crea la agenda de tu consultorio.' : 'Registra tu acceso profesional.' }}</h1>
            </div>

        </div>
    </section>

    <section class="flex min-h-screen items-center px-5 py-10 sm:px-8 lg:px-12">
        <div class="mx-auto w-full max-w-3xl">
            <a href="{{ route('login') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-[#9700cf] transition hover:text-[#512569]">
                <span aria-hidden="true">←</span>
                Volver al inicio de sesión
            </a>

            <div class="mt-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.24em] text-[#9700cf]">Registro</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-[#21152e] sm:text-4xl">{{ $creatingClinic ? 'Crear cuenta y agenda' : (!empty($acceptClinicInvitation) ? 'Aceptar invitación' : 'Crear cuenta de '.($selectedRole === 'doctor' ? 'doctor' : 'asistente')) }}</h2>
                    @isset($clinicName)
                        <p class="mt-2 text-sm text-slate-600">Te unirás a {{ $clinicName }} como {{ $selectedRole === 'doctor' ? 'dentista' : 'asistente' }}.</p>
                    @endisset
                </div>
            </div>

            <div class="mt-8">
                <form method="POST" action="{{ isset($clinicName) || isset($invitedEmail) ? request()->fullUrl() : route('register') }}" class="rounded-[1.35rem] border border-[#ead2fb] bg-white p-6 shadow-[0_18px_60px_rgba(81,37,105,.08)]">
                    @csrf
                    @unless($creatingClinic)
                        <input type="hidden" name="role" value="{{ $selectedRole }}">
                    @endunless

                    @if ($errors->any())
                        <div class="mb-5 rounded-2xl border border-fuchsia-200 bg-fuchsia-50 px-4 py-3 text-sm font-semibold text-fuchsia-700">
                            @foreach ($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    @if(!empty($acceptClinicInvitation))
                        <p class="mb-5 text-sm text-slate-600">Sesión actual: {{ $invitedEmail }}</p>
                        @if(!empty($alreadyInvitedMember))
                            <p class="mb-5 text-sm text-slate-600">Esta cuenta ya pertenece al consultorio. Si el enlace era para otra persona, puede abrirlo en su dispositivo o crear su cuenta aquí.</p>
                        @else
                            <button type="submit" class="w-full rounded-xl bg-[#9700cf] py-3.5 text-sm font-bold text-white">Aceptar con esta cuenta</button>
                        @endif
                        @if(!empty($canSwitchInvitationAccount))
                            <button type="submit" name="invitation_switch_account" value="1" class="mt-3 w-full rounded-xl border border-[#ead2fb] bg-white px-4 py-3 text-sm font-bold text-[#9700cf]">Cerrar sesión y crear otra cuenta</button>
                        @endif
                    @else
                    <div class="mb-6 flex items-center gap-3">
                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#ead2fb] text-[#9700cf]">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 4 7v5c0 5 3.4 8 8 9 4.6-1 8-4 8-9V7l-8-4Z"/><path d="m9 12 2 2 4-5"/></svg>
                        </span>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-[#ad79d3]">Datos de acceso</p>
                            <h3 class="text-xl font-bold text-[#21152e]">Información de la cuenta</h3>
                        </div>
                    </div>

                    <div class="space-y-5">
                        <div>
                            <label for="name" class="mb-2 block text-sm font-semibold text-slate-700">{{ $creatingClinic ? 'Nombre' : 'Nombre completo' }}</label>
                            <input id="name" type="text" name="name" value="{{ old('name') }}" autocomplete="{{ $creatingClinic ? 'given-name' : 'name' }}" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]">
                        </div>

                        @if($creatingClinic)
                            <div>
                                <label for="last_name" class="mb-2 block text-sm font-semibold text-slate-700">Apellidos</label>
                                <input id="last_name" type="text" name="last_name" value="{{ old('last_name') }}" maxlength="127" autocomplete="family-name" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]">
                            </div>
                            <div>
                                <label for="clinic_name" class="mb-2 block text-sm font-semibold text-slate-700">Nombre del consultorio</label>
                                <input id="clinic_name" type="text" name="clinic_name" value="{{ old('clinic_name') }}" maxlength="255" autocomplete="organization" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]">
                            </div>
                        @endif

                        <div>
                            <label for="email" class="mb-2 block text-sm font-semibold text-slate-700">Correo electrónico</label>
                            <input id="email" type="email" name="email" value="{{ $invitedEmail ?? old('email') }}" @readonly(isset($invitedEmail)) placeholder="nombre@clinica.com" autocomplete="email" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]">
                        </div>

                        @unless($creatingClinic)
                        <fieldset>
                            <legend class="mb-2 block text-sm font-semibold text-slate-700">Genero</legend>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3.5 text-sm font-semibold text-slate-700 transition has-[:checked]:border-[#9700cf] has-[:checked]:bg-[#fbf0ff] has-[:checked]:text-[#9700cf] has-[:focus]:ring-4 has-[:focus]:ring-[#ead2fb]">
                                    <input type="radio" name="gender" value="hombre" required @checked(old('gender') === 'hombre') class="h-4 w-4 border-slate-300 text-[#9700cf] focus:ring-[#9700cf]">
                                    Hombre
                                </label>
                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3.5 text-sm font-semibold text-slate-700 transition has-[:checked]:border-[#9700cf] has-[:checked]:bg-[#fbf0ff] has-[:checked]:text-[#9700cf] has-[:focus]:ring-4 has-[:focus]:ring-[#ead2fb]">
                                    <input type="radio" name="gender" value="mujer" required @checked(old('gender') === 'mujer') class="h-4 w-4 border-slate-300 text-[#9700cf] focus:ring-[#9700cf]">
                                    Mujer
                                </label>
                            </div>
                        </fieldset>
                        @endunless

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="password" class="mb-2 block text-sm font-semibold text-slate-700">Contraseña</label>
                                <div class="relative">
                                    <input id="password" type="password" name="password" placeholder="********" autocomplete="new-password" required data-password-strength-input class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3.5 pr-12 text-sm outline-none transition focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]">
                                    <button type="button" data-password-toggle aria-controls="password" aria-label="Mostrar contraseña" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-slate-400 transition hover:text-[#9700cf]">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.75"/></svg>
                                    </button>
                                </div>
                                <div class="mt-3" data-password-strength>
                                    <div class="mb-2 flex items-center justify-between gap-3">
                                        <p class="text-xs font-bold uppercase tracking-wider text-[#ad79d3]">Seguridad</p>
                                        <p class="text-xs font-bold text-slate-500" data-password-strength-label>Sin evaluar</p>
                                    </div>
                                    <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full w-0 rounded-full bg-slate-300 transition-all duration-300" data-password-strength-bar></div>
                                    </div>
                                    <ul class="mt-3 grid gap-1.5 text-xs font-semibold text-slate-500" data-password-strength-rules>
                                        <li data-rule="length">Mínimo 8 caracteres</li>
                                        <li data-rule="case" class="hidden">Mayúsculas y minúsculas</li>
                                        <li data-rule="number" class="hidden">Al menos un número</li>
                                        <li data-rule="symbol" class="hidden">Al menos un símbolo</li>
                                    </ul>
                                    <p class="mt-3 hidden text-xs font-semibold text-emerald-600" data-password-requirements-complete>Todos los requisitos se han cumplido.</p>
                                </div>
                            </div>

                            <div>
                                <label for="password_confirmation" class="mb-2 block text-sm font-semibold text-slate-700">Confirmar contraseña</label>
                                <div class="relative">
                                    <input id="password_confirmation" type="password" name="password_confirmation" placeholder="********" autocomplete="new-password" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3.5 pr-12 text-sm outline-none transition focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]">
                                    <button type="button" data-password-toggle aria-controls="password_confirmation" aria-label="Mostrar contraseña" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-slate-400 transition hover:text-[#9700cf]">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.75"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="w-full rounded-xl bg-[#9700cf] py-3.5 text-sm font-bold text-white shadow-lg shadow-[#d3a2f2] transition hover:bg-[#512569]">{{ $creatingClinic ? 'Crear mi agenda' : (isset($clinicName) ? 'Crear cuenta y aceptar' : 'Enviar solicitud de acceso') }}</button>
                        @if(isset($clinicName) && !isset($invitedEmail))
                            <button type="submit" name="invitation_login" value="1" formnovalidate class="mt-3 w-full text-sm font-bold text-[#9700cf]">Ya tengo cuenta, iniciar sesión</button>
                        @endif
                    </div>
                    @endif
                </form>
            </div>

            <footer class="mt-8 text-center text-xs text-[#8a7399]">
                ¿Ya tienes una cuenta? <a href="{{ route('login') }}" class="font-bold text-[#9700cf]">Iniciar sesión</a>
            </footer>
        </div>
    </section>
</main>
<script>
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.getAttribute('aria-controls'));
            if (!input) return;

            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            button.setAttribute('aria-label', isHidden ? 'Ocultar contraseña' : 'Mostrar contraseña');
        });
    });

    const registrationPassword = document.querySelector('[data-password-strength-input]');
    const passwordRequirements = [...document.querySelectorAll('[data-password-strength-rules] [data-rule]')];
    const passwordRequirementsComplete = document.querySelector('[data-password-requirements-complete]');

    const updateVisiblePasswordRequirement = () => {
        if (!registrationPassword || !passwordRequirements.length) return;

        const value = registrationPassword.value;
        const rules = {
            length: value.length >= 8,
            case: /[a-z]/.test(value) && /[A-Z]/.test(value),
            number: /\d/.test(value),
            symbol: /[^A-Za-z0-9]/.test(value),
        };
        const nextRule = passwordRequirements.find((requirement) => !rules[requirement.dataset.rule]);

        passwordRequirements.forEach((requirement) => {
            requirement.classList.toggle('hidden', requirement !== nextRule);
        });
        passwordRequirementsComplete?.classList.toggle('hidden', Boolean(nextRule));
    };

    registrationPassword?.addEventListener('input', updateVisiblePasswordRequirement);
    updateVisiblePasswordRequirement();
</script>
</body>
</html>
