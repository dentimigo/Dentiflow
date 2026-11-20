@extends('layouts.app')

@section('title', 'Editar usuario')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <section>
        <h1 class="text-3xl font-bold tracking-tight text-slate-900">Editar usuario</h1>
        <p class="mt-2 text-sm text-slate-500">Actualiza los datos y asigna el rol correspondiente al usuario.</p>
    </section>

    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_4px_20px_rgba(30,34,60,.04)] sm:p-8">
        @csrf
        @method('PUT')

        @if($errors->any())
            <div class="mb-6 rounded-xl border border-fuchsia-200 bg-fuchsia-50 p-4 text-sm text-fuchsia-700">
                @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <div class="grid gap-5 sm:grid-cols-2">
            <label class="block text-sm font-semibold text-slate-700">Nombre
                <input name="name" value="{{ old('name', $user->name) }}" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3.5 font-normal outline-none focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]">
            </label>
            <label class="block text-sm font-semibold text-slate-700">Correo
                <input type="email" name="email" value="{{ old('email', $user->email) }}" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3.5 font-normal outline-none focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]">
            </label>
            <label class="block text-sm font-semibold text-slate-700 sm:col-span-2">Rol
                <select name="role" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3.5 font-normal outline-none focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]">
                    @foreach([
                        'pendiente' => 'Pendiente de autorizacion',
                        'doctor' => 'Doctor',
                        'asistente' => 'Asistente',
                    ] as $value => $label)
                        <option value="{{ $value }}" @selected(old('role', $user->role) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <div class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500 sm:col-span-2">
                <p class="font-bold text-[#9700cf]">Rol actual: {{ $user->role }}</p>
                @if($user->requested_role)
                    <p class="mt-2 font-semibold text-amber-700">Acceso solicitado: {{ $user->requested_role === 'doctor' ? 'Doctor' : 'Asistente' }}</p>
                @endif
                <p class="mt-2 text-sm">{{ $user->dentist ? 'Doctor vinculado: '.$user->dentist->nombre : 'Este usuario no esta vinculado a un perfil clinico.' }}</p>
            </div>
        </div>

        <div class="mt-7 flex justify-end gap-3">
            <a href="{{ route('admin.users') }}" class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Cancelar</a>
            <button class="rounded-xl bg-[#9700cf] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-[#d3a2f2] transition hover:bg-[#8200b8]">Guardar cambios</button>
        </div>
    </form>
</div>
@endsection
