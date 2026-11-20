@extends('layouts.app')

@section('title','Agregar Dentista')

@section('content')

<div>
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-4xl font-bold text-gray-800">Nuevo Dentista</h1>
            <p class="mt-2 text-gray-500">Registra un nuevo profesional al equipo.</p>
        </div>
        <a href="{{ route('dentistas') }}" class="bg-gray-200 text-gray-800 px-6 py-3 rounded-2xl hover:bg-gray-300">Volver</a>
    </div>

    <div class="bg-white rounded-3xl shadow-lg p-8">
        @if ($errors->any())
            <div class="mb-6 rounded-3xl bg-fuchsia-50 border border-fuchsia-200 p-4 text-fuchsia-700">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('dentistas.store') }}" method="POST" class="space-y-6">
            @csrf

            <div class="grid gap-6 md:grid-cols-2">
                <label class="block">
                    <span class="text-gray-700">Nombre</span>
                    <input type="text" name="nombre" value="{{ old('nombre') }}" class="mt-2 w-full rounded-2xl border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-purple-300">
                </label>

                <label class="block">
                    <span class="text-gray-700">Especialidad</span>
                    <input type="text" name="especialidad" value="{{ old('especialidad') }}" class="mt-2 w-full rounded-2xl border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-purple-300">
                </label>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <label class="block">
                    <span class="text-gray-700">Teléfono</span>
                    <input type="text" name="telefono" value="{{ old('telefono') }}" class="mt-2 w-full rounded-2xl border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-purple-300">
                </label>
            </div>

            <div class="rounded-3xl border border-[#ead2fb] bg-[#f6e8ff]/50 p-5">
                <h2 class="text-sm font-bold text-gray-800">Acceso del doctor</h2>
                <p class="mt-1 text-xs text-gray-500">Estos datos se usarán para iniciar sesión con perfil de doctor.</p>
                <div class="mt-5 grid gap-6 md:grid-cols-2">
                    <label class="block">
                        <span class="text-gray-700">Correo de acceso</span>
                        <input type="email" name="email" value="{{ old('email') }}" class="mt-2 w-full rounded-2xl border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-purple-300">
                    </label>
                    <label class="block">
                        <span class="text-gray-700">Contraseña</span>
                        <input type="password" name="password" class="mt-2 w-full rounded-2xl border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-purple-300">
                    </label>
                    <label class="block md:col-span-2">
                        <span class="text-gray-700">Confirmar contraseña</span>
                        <input type="password" name="password_confirmation" class="mt-2 w-full rounded-2xl border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-purple-300">
                    </label>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-[#9700cf] text-white px-8 py-3 rounded-2xl hover:bg-[#512569]">Guardar dentista</button>
            </div>
        </form>
    </div>
</div>

@endsection



