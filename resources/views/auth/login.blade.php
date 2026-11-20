<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión - DentiFlow</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="min-h-screen bg-white font-['DM_Sans'] text-[#21152e] antialiased">
<main class="relative min-h-screen overflow-hidden bg-white">
    <div class="absolute inset-x-0 top-0 h-[56vh] bg-gradient-to-b from-[#512569] via-[#78358c] to-white"></div>
    <div class="absolute -left-24 top-10 h-72 w-72 rounded-full bg-[#9700cf]/20 blur-3xl"></div>
    <div class="absolute -right-24 top-20 h-80 w-80 rounded-full bg-white/10 blur-3xl"></div>

    <div class="relative z-10 flex min-h-screen flex-col px-5 py-7 sm:px-8 sm:py-9">
        <header class="flex items-center justify-center gap-3 text-white">
            <span class="flex h-11 w-11 items-center justify-center rounded-2xl border border-white/15 bg-white/10 backdrop-blur-sm">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M7.3 3.7c1.8-.8 3 .3 4.7.3s2.9-1.1 4.7-.3c2.6 1.2 3 4.8 1.9 7.3-.9 2.1-1.4 3.4-1.6 6.6-.1 1.7-.9 3.4-2.1 3.4-1.7 0-1.2-4.8-2.9-4.8S10.8 21 9.1 21C7.9 21 7.1 19.3 7 17.6c-.2-3.2-.7-4.5-1.6-6.6C4.3 8.5 4.7 4.9 7.3 3.7Z"/></svg>
            </span>
            <span class="text-xl font-bold tracking-tight">DentiFlow</span>
        </header>

        <section class="flex flex-1 items-center justify-center py-8 sm:py-10">
            <div class="w-full max-w-md rounded-[2rem] bg-white/95 p-6 shadow-[0_24px_70px_rgba(65,27,78,.15)] backdrop-blur-sm sm:p-9">
            @if(session('warning'))
                <div class="mb-6 rounded-2xl border border-fuchsia-200 bg-fuchsia-50 px-4 py-3 text-sm font-semibold text-fuchsia-700">{{ session('warning') }}</div>
            @endif
            @include('partials.flash-success')

            <div>
                <form method="POST" action="{{ route('login') }}" autocomplete="{{ session('login_credentials_failed') ? 'off' : 'on' }}">
                    @csrf

                    @if ($errors->any())
                        <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                            @foreach ($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    <div class="mb-8 flex flex-col items-center text-center">
                        <span class="flex h-14 w-14 items-center justify-center rounded-full bg-[#f1e2f8] text-[#9700cf]">
                            <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <circle cx="12" cy="7" r="4"/>
                                <path d="M5 21v-2a7 7 0 0 1 14 0v2"/>
                            </svg>
                        </span>
                        <h1 class="mt-4 text-3xl font-bold tracking-tight text-[#21152e]">Iniciar sesión</h1>
                    </div>

                    <div class="space-y-5">
                        <div>
                            <label for="email" class="mb-2 block text-base font-semibold text-[#3d3046]">Correo electrónico</label>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="nombre@clinica.com" autocomplete="email" required class="w-full rounded-2xl border border-[#e6dce9] bg-white px-4 py-4 text-base outline-none transition placeholder:text-slate-400 focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]/70">
                        </div>

                        <div>
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <label for="password" class="text-base font-semibold text-[#3d3046]">Contraseña</label>
                                <a id="recover-password" href="{{ route('password.forgot') }}" class="text-sm font-semibold text-[#9700cf] transition hover:text-[#512569]">¿Olvidaste tu contraseña?</a>
                            </div>
                            <div class="relative">
                                <input id="password" type="password" name="password" value="" placeholder="Ingresa tu contraseña" autocomplete="{{ session('login_credentials_failed') ? 'new-password' : 'current-password' }}" required class="w-full rounded-2xl border border-[#e6dce9] bg-white py-4 pl-4 pr-12 text-base outline-none transition placeholder:text-slate-400 focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]/70">
                                <button id="toggle-password" type="button" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center rounded-r-2xl text-slate-400 transition hover:text-[#9700cf] focus:outline-none focus:ring-4 focus:ring-inset focus:ring-[#ead2fb]/70" aria-label="Mostrar contraseña" aria-pressed="false">
                                    <svg id="eye-open" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/>
                                        <circle cx="12" cy="12" r="2.5"/>
                                    </svg>
                                    <svg id="eye-closed" class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="m3 3 18 18"/>
                                        <path d="M10.6 6.2A10.7 10.7 0 0 1 12 6c6 0 9.5 6 9.5 6a16.3 16.3 0 0 1-2.1 2.8M6.2 6.2C3.8 7.8 2.5 12 2.5 12s3.5 6 9.5 6a9.8 9.8 0 0 0 3.2-.5"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="w-full rounded-2xl bg-gradient-to-r from-[#9700cf] to-[#7d16a3] py-4 text-base font-bold text-white shadow-[0_12px_28px_rgba(151,0,207,.2)] transition hover:from-[#7d16a3] hover:to-[#512569] focus:outline-none focus:ring-4 focus:ring-[#ead2fb]">Iniciar sesión</button>
                    </div>

                    <p class="mt-7 text-center text-sm text-[#715f7c]">
                        ¿No tienes cuenta?
                        <a href="{{ route('register') }}" class="ml-1 font-bold text-[#9700cf] transition hover:text-[#512569]">Crear cuenta</a>
                    </p>
                </form>
                <p class="mt-3 text-center text-sm">
                    <a href="{{ route('marketing.dentiflow') }}" class="font-semibold text-[#512569] underline">Conoce DentiFlow</a>
                </p>
            </div>
            </div>
        </section>
    </div>
</main>
@if(session('logout_success'))
    <div id="logout-toast" role="status" aria-live="polite" class="fixed right-3 top-3 z-50 flex w-[calc(100%-1.5rem)] max-w-sm items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800 shadow-xl transition duration-500 sm:right-5 sm:top-5 sm:w-auto">
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>
        </span>
        <span>{{ session('logout_success') }}</span>
    </div>
@endif
<script>
    const passwordInput = document.getElementById('password');
    const togglePassword = document.getElementById('toggle-password');
    const eyeOpen = document.getElementById('eye-open');
    const eyeClosed = document.getElementById('eye-closed');

    @if(session('login_credentials_failed'))
        // Algunos gestores de contraseñas rellenan el campo después de que carga
        // la página. En un intento fallido siempre debe quedar vacío.
        const clearFailedPassword = () => {
            passwordInput.value = '';
        };

        clearFailedPassword();
        window.addEventListener('pageshow', clearFailedPassword);
        window.setTimeout(clearFailedPassword, 100);
        window.setTimeout(clearFailedPassword, 500);
    @endif

    togglePassword.addEventListener('click', () => {
        const passwordIsVisible = passwordInput.type === 'text';

        passwordInput.type = passwordIsVisible ? 'password' : 'text';
        eyeOpen.classList.toggle('hidden', !passwordIsVisible);
        eyeClosed.classList.toggle('hidden', passwordIsVisible);
        togglePassword.setAttribute('aria-label', passwordIsVisible ? 'Mostrar contraseña' : 'Ocultar contraseña');
        togglePassword.setAttribute('aria-pressed', String(!passwordIsVisible));
    });

    const recoverLink = document.getElementById('recover-password');
    const emailInput = document.getElementById('email');

    recoverLink.addEventListener('click', (event) => {
        const emailValue = emailInput.value.trim();
        if (!emailValue) {
            return;
        }

        const url = new URL(recoverLink.href, window.location.origin);
        url.searchParams.set('email', emailValue);
        recoverLink.href = url.toString();
    });

    const logoutToast = document.getElementById('logout-toast');
    if (logoutToast) {
        window.setTimeout(() => {
            logoutToast.classList.add('translate-y-2', 'opacity-0');
            window.setTimeout(() => logoutToast.remove(), 500);
        }, 4000);
    }
</script>
</body>
</html>
