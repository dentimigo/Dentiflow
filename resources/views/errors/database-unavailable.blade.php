<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Servicio temporalmente no disponible · DentiFlow</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="min-h-screen bg-[#fbf0ff] font-['DM_Sans'] text-[#21152e] antialiased">
<main class="relative flex min-h-screen items-center justify-center overflow-hidden px-5 py-10 sm:px-8">
    <div class="absolute inset-0 opacity-60" style="background-image: radial-gradient(circle at 15% 20%, rgba(151,0,207,.12), transparent 28%), radial-gradient(circle at 85% 80%, rgba(81,37,105,.12), transparent 30%);"></div>

    <section class="relative w-full max-w-xl overflow-hidden rounded-[2rem] border border-[#ead2fb] bg-white shadow-[0_24px_80px_rgba(81,37,105,.14)]">
        <div class="h-2 bg-gradient-to-r from-[#512569] via-[#9700cf] to-[#d3a2f2]"></div>
        <div class="p-6 sm:p-10">
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#512569] text-white shadow-lg shadow-[#d3a2f2]">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M7.3 3.7c1.8-.8 3 .3 4.7.3s2.9-1.1 4.7-.3c2.6 1.2 3 4.8 1.9 7.3-.9 2.1-1.4 3.4-1.6 6.6-.1 1.7-.9 3.4-2.1 3.4-1.7 0-1.2-4.8-2.9-4.8S10.8 21 9.1 21C7.9 21 7.1 19.3 7 17.6c-.2-3.2-.7-4.5-1.6-6.6C4.3 8.5 4.7 4.9 7.3 3.7Z"/></svg>
                </span>
                <div>
                    <p class="font-bold leading-none">DentiFlow</p>
                    <p class="mt-1 text-[10px] font-bold uppercase tracking-[0.2em] text-[#9700cf]">Gestión clínica</p>
                </div>
            </div>

            <div class="mt-9 flex h-16 w-16 items-center justify-center rounded-2xl bg-amber-50 text-amber-600 ring-1 ring-amber-200">
                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path d="M12 3 2.8 19h18.4L12 3Z"/>
                    <path d="M12 9v4m0 3h.01"/>
                </svg>
            </div>

            <p class="mt-7 text-xs font-bold uppercase tracking-[0.18em] text-amber-600">Conexión temporalmente interrumpida</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-[#21152e] sm:text-4xl">No pudimos conectar con la base de datos</h1>
            <p class="mt-4 text-base leading-7 text-slate-500">Tus datos están protegidos. El servicio puede estar iniciando o reiniciándose; espera unos segundos y vuelve a intentarlo.</p>

            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                <button type="button" onclick="window.location.reload()" class="inline-flex min-h-12 flex-1 items-center justify-center rounded-xl bg-[#9700cf] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-[#d3a2f2] transition hover:bg-[#512569] focus:outline-none focus:ring-4 focus:ring-[#ead2fb]">
                    Intentar de nuevo
                </button>
                <a href="{{ route('login') }}" class="inline-flex min-h-12 flex-1 items-center justify-center rounded-xl border border-[#ead2fb] bg-[#fbf0ff] px-5 py-3 text-sm font-bold text-[#512569] transition hover:bg-[#ead2fb] focus:outline-none focus:ring-4 focus:ring-[#ead2fb]">
                    Volver al inicio
                </a>
            </div>

            <p class="mt-7 border-t border-slate-100 pt-5 text-center text-xs text-slate-400">Si el mensaje continúa, verifica que MySQL esté iniciado en el panel de XAMPP.</p>
        </div>
    </section>
</main>
</body>
</html>
