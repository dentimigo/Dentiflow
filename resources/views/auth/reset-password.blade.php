<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer contraseña - DentiFlow</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="min-h-screen bg-[#fbf0ff] font-['DM_Sans'] text-[#21152e] antialiased">
<main class="flex min-h-screen items-center justify-center px-5 py-10">
    <section class="w-full max-w-md rounded-[1.35rem] border border-[#ead2fb] bg-white p-6 shadow-[0_18px_60px_rgba(81,37,105,.08)]">
        <div class="mb-6 flex items-center gap-3">
            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#ead2fb] text-[#9700cf]">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 5 6v5c0 4.4 2.8 8.4 7 10 4.2-1.6 7-5.6 7-10V6l-7-3Z"/><path d="M9.5 12.2 11.3 14l3.6-4"/></svg>
            </span>
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-[#ad79d3]">Recuperacion</p>
                <h1 class="text-xl font-bold text-[#21152e]">Cambiar contraseña</h1>
            </div>
        </div>

        <p class="mb-5 text-sm leading-6 text-[#6b5778]">Escribe el correo de tu cuenta y tu nueva contraseña. El enlace solo funciona para el correo al que fue enviado.</p>

        @if ($errors->any())
            <div class="mb-5 rounded-2xl border border-fuchsia-200 bg-fuchsia-50 px-4 py-3 text-sm font-semibold text-fuchsia-700">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div>
                <label for="email" class="mb-2 block text-sm font-semibold text-slate-700">Correo electrónico</label>
                <input id="email" type="email" name="email" value="{{ old('email', $email) }}" autocomplete="email" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]">
            </div>

            <div>
                <label for="password" class="mb-2 block text-sm font-semibold text-slate-700">Nueva contraseña</label>
                <input id="password" type="password" name="password" autocomplete="new-password" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]">
            </div>

            <div>
                <label for="password_confirmation" class="mb-2 block text-sm font-semibold text-slate-700">Confirmar nueva contraseña</label>
                <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]">
            </div>

            <button type="submit" class="w-full rounded-xl bg-[#9700cf] py-3.5 text-sm font-bold text-white shadow-lg shadow-[#d3a2f2] transition hover:bg-[#512569]">Actualizar contraseña</button>
        </form>

        <a href="{{ route('login') }}" class="mt-5 inline-flex text-sm font-bold text-[#9700cf] transition hover:text-[#512569]">Volver al inicio de sesión</a>
    </section>
</main>
</body>
</html>
