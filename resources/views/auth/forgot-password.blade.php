<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar contraseña - DentiFlow</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="min-h-screen bg-[#fbf0ff] font-['DM_Sans'] text-[#21152e] antialiased">
<main class="flex min-h-screen items-center justify-center px-5 py-10">
    <section class="w-full max-w-3xl rounded-[1.35rem] border border-[#ead2fb] bg-white p-6 shadow-[0_18px_60px_rgba(81,37,105,.08)]">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-3xl font-bold text-[#21152e]">Recuperar contraseña</h1>
            </div>
            <a href="{{ route('login') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-600 transition hover:border-slate-300">Volver al inicio</a>
        </div>

        @if(session('success'))
            <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-5 rounded-2xl border border-fuchsia-200 bg-fuchsia-50 px-4 py-3 text-sm font-semibold text-fuchsia-700">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-[1fr]">
            <section class="rounded-3xl border border-[#ead2fb] bg-white p-6 mx-auto max-w-2xl">
                <div class="mb-4 flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#ead2fb] text-[#9700cf]">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 11h14v10H5zM8 11V7a4 4 0 0 1 8 0v4"/></svg>
                    </span>
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Validar código</h2>
                        <p class="text-sm text-slate-500">Ingresa el código que fue enviado al correo usado en el inicio de sesión.</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('password.forgot.verify') }}" class="space-y-4">
                    @csrf
                    <label class="block text-sm font-semibold text-slate-600">Código de 6 dígitos
                        <input type="text" name="code" pattern="[0-9]{6}" maxlength="6" inputmode="numeric" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-center outline-none focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]" placeholder="000000">
                    </label>
                    <button type="submit" class="w-full rounded-xl bg-[#512569] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#34154c]">Verificar código</button>
                </form>

                @if(session('warning'))
                    <p class="mt-4 rounded-2xl border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-700">{{ session('warning') }}</p>
                @endif
            </section>
        </div>

        <p class="mt-6 text-center text-sm text-slate-500">El código caduca en 10 minutos. Si no llega, revisa tu carpeta de correo no deseado o regresa al inicio para enviar el código de nuevo.</p>
    </section>
</main>

@if(session('code_sent'))
    <div class="fixed right-5 top-5 z-50 flex w-[calc(100%-2.5rem)] max-w-md flex-col gap-3" aria-live="polite">
        <div data-auto-dismiss class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800 shadow-xl transition duration-500">
            Código enviado al correo {{ session('password_reset_email') }}. Escribe el código recibido.
        </div>

        @if(! session('password_reset_verified'))
            <div data-auto-dismiss class="rounded-2xl border border-violet-200 bg-violet-50 px-5 py-4 text-sm font-semibold text-violet-800 shadow-xl transition duration-500">
                Código enviado. Revisa tu correo y verifica el código antes de continuar.
            </div>
        @endif
    </div>
@endif

<script>
    window.setTimeout(() => {
        document.querySelectorAll('[data-auto-dismiss]').forEach((message) => {
            message.classList.add('translate-y-2', 'opacity-0');
            window.setTimeout(() => message.remove(), 500);
        });
    }, 5000);
</script>
</body>
</html>
