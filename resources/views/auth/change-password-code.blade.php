<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar contraseña - DentiFlow</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="min-h-screen bg-[#fbf0ff] font-['DM_Sans'] text-[#21152e] antialiased">
<main class="flex min-h-screen items-center justify-center px-5 py-10">
    <section class="w-full max-w-3xl rounded-[1.5rem] border border-[#ead2fb] bg-white p-8 shadow-[0_18px_60px_rgba(81,37,105,.08)] sm:p-10">
        <div class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-[#ead2fb] text-[#9700cf]">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 11h14v10H5z"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                    </span>
                    <h1 class="text-3xl font-bold text-[#21152e]">Cambiar contraseña</h1>
                </div>
                <p class="mt-3 text-lg leading-8 text-[#6b5778]">
                    <span class="block">Ingresa tu nueva contraseña.</span>
                    <span class="block">Asegúrate de cumplir con los caracteres requeridos.</span>
                </p>
            </div>
            <a href="{{ route('login') }}" class="rounded-xl border border-slate-200 bg-white px-5 py-3.5 text-base font-semibold text-slate-600 transition hover:border-slate-300">Volver al inicio</a>
        </div>

        @if(session('success'))
            <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-base font-semibold text-emerald-700">{{ session('success') }}</div>
        @endif
        @if(session('warning'))
            <div class="mb-6 rounded-2xl border border-orange-200 bg-orange-50 px-5 py-4 text-base font-semibold text-orange-700">{{ session('warning') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-base font-semibold text-red-700">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('password.forgot.change') }}" class="space-y-6">
            @csrf
            <div>
                <label for="new-password" class="block text-base font-semibold text-slate-600">Nueva contraseña</label>
                <input id="new-password" type="password" name="password" minlength="8" required class="mt-2.5 w-full rounded-xl border border-slate-200 bg-white px-5 py-4 text-base outline-none focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]" placeholder="********">
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                    <div id="password-strength-bar" class="h-full w-0 rounded-full bg-red-400 transition-all duration-300"></div>
                </div>
                <div class="mt-3 flex items-start justify-between gap-4 text-sm">
                    <p class="leading-5 text-slate-500">Usa 8 caracteres o más, mayúscula, minúscula, número y símbolo.</p>
                    <span id="password-strength-label" class="shrink-0 font-bold text-slate-400">Sin evaluar</span>
                </div>
            </div>
            <div>
                <label for="confirm-password" class="block text-base font-semibold text-slate-600">Confirmar contraseña</label>
                <input id="confirm-password" type="password" name="password_confirmation" minlength="8" required class="mt-2.5 w-full rounded-xl border border-slate-200 bg-white px-5 py-4 text-base outline-none focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]" placeholder="********">
            </div>
            <button type="submit" class="w-full rounded-xl bg-[#9700cf] px-6 py-4 text-lg font-bold text-white transition hover:bg-[#512569]">Actualizar contraseña</button>
        </form>
    </section>
</main>
<script>
    const newPasswordInput = document.getElementById('new-password');
    const confirmPasswordInput = document.getElementById('confirm-password');
    const strengthBar = document.getElementById('password-strength-bar');
    const strengthLabel = document.getElementById('password-strength-label');

    const strengthLevels = [
        { label: 'Sin evaluar', width: '0%', color: 'bg-red-400', text: 'text-slate-400' },
        { label: 'Muy débil', width: '20%', color: 'bg-red-500', text: 'text-red-600' },
        { label: 'Débil', width: '40%', color: 'bg-orange-500', text: 'text-orange-600' },
        { label: 'Media', width: '60%', color: 'bg-amber-400', text: 'text-amber-600' },
        { label: 'Fuerte', width: '80%', color: 'bg-lime-500', text: 'text-lime-700' },
        { label: 'Muy fuerte', width: '100%', color: 'bg-emerald-500', text: 'text-emerald-700' },
    ];

    function updateStrength() {
        const password = newPasswordInput.value;
        const score = password === '' ? 0 : [
            password.length >= 8,
            /[a-z]/.test(password),
            /[A-Z]/.test(password),
            /[0-9]/.test(password),
            /[^A-Za-z0-9]/.test(password),
        ].filter(Boolean).length;
        const level = strengthLevels[score];

        strengthBar.style.width = level.width;
        strengthBar.className = `h-full rounded-full transition-all duration-300 ${level.color}`;
        strengthLabel.textContent = level.label;
        strengthLabel.className = `shrink-0 font-bold ${level.text}`;
        validateMatch();
    }

    function validateMatch() {
        const hasConfirmation = confirmPasswordInput.value.length > 0;
        const matches = newPasswordInput.value === confirmPasswordInput.value;
        const showError = hasConfirmation && !matches;

        confirmPasswordInput.classList.toggle('border-red-400', showError);
        confirmPasswordInput.setCustomValidity(showError ? 'Las contraseñas no coinciden.' : '');
    }

    newPasswordInput.addEventListener('input', updateStrength);
    confirmPasswordInput.addEventListener('input', validateMatch);
</script>
</body>
</html>
