@php
    $emergency = $paciente['contacto_emergencia'] ?? [];
    $backUrl = $backUrl ?? route('pacientes');
    $backLabel = $backLabel ?? 'Volver a pacientes';
    $fieldClass = 'mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-300 focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]';
    $lettersOnly = "this.value = this.value.replace(/[^A-Za-zÁÉÍÓÚáéíóúÑñÜü\\s]/g, '')";
    $numbersOnly = "this.value = this.value.replace(/[^0-9]/g, '')";
@endphp
<div class="mx-auto max-w-6xl space-y-6">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div><h1 class="text-3xl font-bold tracking-tight">{{ $title }}</h1><p class="mt-2 text-sm text-slate-500">{{ $subtitle }}</p></div>
        <a href="{{ $backUrl }}" class="inline-flex justify-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-600 shadow-sm">{{ $backLabel }}</a>
    </header>

    @if($errors->any())<div class="rounded-xl border border-fuchsia-200 bg-fuchsia-50 p-4 text-sm text-fuchsia-700"><p class="mb-2 font-bold">Revisa la información:</p>@foreach($errors->all() as $error)<p>• {{ $error }}</p>@endforeach</div>@endif

    <form method="POST" action="{{ $action }}" class="space-y-5">
        @csrf
        @if($method !== 'POST') @method($method) @endif
        @if(!empty($origin))<input type="hidden" name="origen" value="{{ $origin }}">@endif

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_4px_20px_rgba(30,34,60,.04)]">
            <div class="flex items-center gap-3 border-b border-slate-100 px-6 py-5"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#f6e8ff] text-[#9700cf]"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="7" r="4"/><path d="M5 22v-3a7 7 0 0 1 14 0v3"/></svg></span><div><h2 class="font-bold text-slate-800">Información personal</h2><p class="text-xs text-slate-400">Datos principales para identificar al paciente</p></div></div>
            <div class="grid gap-5 p-6 md:grid-cols-2">
                <label class="text-sm font-semibold text-slate-700">Nombre completo <span class="text-fuchsia-500">*</span><input name="nombre" value="{{ old('nombre', $paciente['nombre'] ?? '') }}" placeholder="Nombre y apellidos" required pattern="[A-Za-zÁÉÍÓÚáéíóúÑñÜü\s]+" title="Solo se permiten letras y espacios." oninput="{{ $lettersOnly }}" class="{{ $fieldClass }}"></label>
                <label class="text-sm font-semibold text-slate-700">Correo electrónico<input type="email" name="email" value="{{ old('email', $paciente['email'] ?? '') }}" placeholder="paciente@correo.com" class="{{ $fieldClass }}"><span class="mt-1.5 block text-[10px] font-normal text-slate-400">Se utilizará para recordatorios y comunicaciones.</span></label>
                <label class="text-sm font-semibold text-slate-700">
                    <span class="flex items-center justify-between gap-3">
                        <span>Teléfono <span class="text-fuchsia-500">*</span></span>
                        @if($method === 'POST')
                            <button id="pick-patient-contact" type="button" class="hidden rounded-lg bg-[#f6e8ff] px-3 py-1.5 text-xs font-bold text-[#9700cf] transition hover:bg-[#ead2fb]">Elegir de contactos</button>
                        @endif
                    </span>
                    <input id="patient-phone" type="tel" name="telefono" value="{{ old('telefono', $paciente['telefono'] ?? '') }}" placeholder="2220000000" required inputmode="numeric" minlength="10" maxlength="10" pattern="[0-9]{10}" title="Ingresa exactamente 10 números." oninput="{{ $numbersOnly }}" class="{{ $fieldClass }}">
                    <span id="contact-picker-help" class="mt-1.5 block text-[10px] font-normal text-slate-400">Debe contener exactamente 10 números.</span>
                </label>
                <label class="text-sm font-semibold text-slate-700">Dirección @if($method === 'POST')<span class="text-fuchsia-500">*</span>@endif<input name="direccion" value="{{ old('direccion', $paciente['direccion'] ?? '') }}" placeholder="Calle, número, colonia y ciudad" @if($method === 'POST') required @endif class="{{ $fieldClass }}"></label>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_4px_20px_rgba(30,34,60,.04)]">
            <div class="flex items-center gap-3 border-b border-slate-100 px-6 py-5"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#f6e8ff] text-[#9700cf]"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2ZM7 2v4m10-4v4M3 9h18"/></svg></span><div><h2 class="font-bold text-slate-800">Información del expediente</h2><p class="text-xs text-slate-400">Seguimiento y cobertura del paciente</p></div></div>
            <div class="grid gap-5 p-6 md:grid-cols-2 lg:grid-cols-3">
                <label class="text-sm font-semibold text-slate-700">Última visita
                    <input type="date" name="ultima_visita" value="{{ old('ultima_visita', $paciente['ultima_visita_input'] ?? '') }}" class="{{ $fieldClass }}">
                    <span class="mt-1.5 block text-[10px] font-normal text-slate-400">Si aun no ha tenido visita, deja este campo vacio; se guardara como Nuevo.</span>
                </label>
                <label class="text-sm font-semibold text-slate-700">Seguro dental<input name="seguro" value="{{ old('seguro', $paciente['seguro'] ?? '') }}" placeholder="Nombre de aseguradora" class="{{ $fieldClass }}"></label>
                <label class="text-sm font-semibold text-slate-700">Número de póliza<input name="poliza" value="{{ old('poliza', $paciente['poliza'] ?? '') }}" placeholder="Póliza o membresía" class="{{ $fieldClass }}"></label>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_4px_20px_rgba(30,34,60,.04)]">
            <div class="flex items-center gap-3 border-b border-slate-100 px-6 py-5"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-violet-50 text-violet-600">!</span><div><h2 class="font-bold text-slate-800">Contacto de emergencia</h2><p class="text-xs text-slate-400">Información opcional para situaciones importantes</p></div></div>
            <div class="grid gap-5 p-6 md:grid-cols-3">
                <label class="text-sm font-semibold text-slate-700">Nombre<input name="contacto_emergencia[nombre]" value="{{ old('contacto_emergencia.nombre', $emergency['nombre'] ?? '') }}" placeholder="Nombre del contacto" pattern="[A-Za-zÁÉÍÓÚáéíóúÑñÜü\s]+" title="Solo se permiten letras y espacios." oninput="{{ $lettersOnly }}" class="{{ $fieldClass }}"></label>
                <label class="text-sm font-semibold text-slate-700">Teléfono<input type="tel" name="contacto_emergencia[telefono]" value="{{ old('contacto_emergencia.telefono', $emergency['telefono'] ?? '') }}" placeholder="2220000000" inputmode="numeric" minlength="10" maxlength="10" pattern="[0-9]{10}" title="Ingresa exactamente 10 números." oninput="{{ $numbersOnly }}" class="{{ $fieldClass }}"><span class="mt-1.5 block text-[10px] font-normal text-slate-400">Si lo proporcionas, debe contener 10 números.</span></label>
                <label class="text-sm font-semibold text-slate-700">Parentesco<input name="contacto_emergencia[relacion]" value="{{ old('contacto_emergencia.relacion', $emergency['relacion'] ?? '') }}" placeholder="Ej. Familiar, pareja" class="{{ $fieldClass }}"></label>
            </div>
        </section>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><a href="{{ $backUrl }}" class="rounded-xl border border-slate-200 bg-white px-6 py-3 text-center text-sm font-semibold text-slate-600">Cancelar</a><button class="rounded-xl bg-[#9700cf] px-6 py-3 text-sm font-bold text-white shadow-lg shadow-[#d3a2f2]">{{ $submitLabel }}</button></div>
    </form>
</div>

@if($method === 'POST')
<script>
    (() => {
        const button = document.getElementById('pick-patient-contact');
        const phoneInput = document.getElementById('patient-phone');
        const nameInput = document.querySelector('input[name="nombre"]');
        const help = document.getElementById('contact-picker-help');
        const supported = 'contacts' in navigator && 'ContactsManager' in window && window.isSecureContext;

        if (!button || !phoneInput || !supported) return;
        button.classList.remove('hidden');

        button.addEventListener('click', async () => {
            try {
                const contacts = await navigator.contacts.select(['name', 'tel'], { multiple: false });
                const contact = contacts[0];
                if (!contact) return;

                const digits = String(contact.tel?.[0] || '').replace(/\D/g, '').slice(-10);
                if (digits.length === 10) phoneInput.value = digits;
                if (nameInput && !nameInput.value.trim() && contact.name?.[0]) nameInput.value = contact.name[0];
                if (help) help.textContent = digits.length === 10
                    ? 'Contacto importado. Revisa los datos antes de guardar.'
                    : 'El contacto no contiene un teléfono válido de 10 dígitos.';
            } catch (error) {
                if (error?.name !== 'AbortError' && help) {
                    help.textContent = 'No fue posible abrir los contactos. Puedes escribir el número manualmente.';
                }
            }
        });
    })();
</script>
@endif


