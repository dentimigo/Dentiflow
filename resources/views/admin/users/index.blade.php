@extends('layouts.app')

@section('title', 'Usuarios del sistema')

@section('content')
@php
    $roleOptions = [
        'pendiente' => 'Pendiente',
        'doctor' => 'Doctor',
        'asistente' => 'Asistente',
    ];

    $roleBadges = [
        'pendiente' => 'bg-amber-50 text-amber-700',
        'doctor' => 'bg-[#f6e8ff] text-[#9700cf]',
        'asistente' => 'bg-violet-50 text-violet-700',
    ];
@endphp
<div class="space-y-6">
    <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900">Usuarios del sistema</h1>
            <p class="mt-2 max-w-2xl text-sm text-slate-500">Revisa solicitudes y administra el acceso de doctores y asistentes.</p>
        </div>
    </section>

    @include('partials.flash-success')
    @if($errors->any())
        <div class="rounded-xl border border-fuchsia-200 bg-fuchsia-50 px-4 py-3 text-sm font-semibold text-fuchsia-700">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <section class="rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="font-bold text-slate-900">Invitar gente</h2>
        <p class="mt-2 text-sm text-slate-500">Genera un enlace de un solo uso válido por 3 días. La cuenta aparecerá en Solicitudes pendientes cuando la persona complete su registro.</p>
        @if(session('invite_link'))
            <div class="mt-4 rounded-xl border border-violet-200 bg-violet-50 p-4">
                <label for="invite-link" class="block text-sm font-semibold text-slate-700">El correo saliente está desactivado. Copia y comparte este enlace con la persona invitada.</label>
                <input id="invite-link" type="text" readonly value="{{ session('invite_link') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
            </div>
        @endif
        <form method="POST" action="{{ route('admin.users.invite') }}" class="mt-4 flex flex-wrap items-end gap-3">
            @csrf
            <div class="min-w-0 flex-1">
                <label for="invite-email" class="mb-1 block text-sm font-semibold text-slate-700">Correo electrónico</label>
                <input id="invite-email" name="invite_email" type="email" value="{{ old('invite_email') }}" maxlength="255" required class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
            </div>
            <div>
                <label for="invite-role" class="mb-1 block text-sm font-semibold text-slate-700">Rol</label>
                <select id="invite-role" name="invite_role" required class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
                    <option value="">Selecciona un rol</option>
                    <option value="asistente" @selected(old('invite_role') === 'asistente')>Asistente</option>
                    <option value="doctor" @selected(old('invite_role') === 'doctor')>Dentista</option>
                </select>
            </div>
            <button class="rounded-xl bg-[#9700cf] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#8200b8]">Invitar</button>
        </form>
    </section>

    <section class="overflow-hidden rounded-2xl border border-amber-200 bg-white shadow-[0_4px_24px_rgba(30,34,60,.05)]">
        <div class="flex flex-col gap-3 border-b border-amber-100 bg-amber-50/70 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
            <div>
                <div class="flex items-center gap-2">
                    <span class="flex h-8 min-w-8 items-center justify-center rounded-full bg-amber-500 px-2 text-xs font-bold text-white">{{ $pendingUsers->count() }}</span>
                    <h2 class="font-bold text-slate-900">Solicitudes pendientes</h2>
                </div>
                <p class="mt-2 text-xs text-slate-500">Revisa el perfil solicitado antes de conceder acceso al sistema.</p>
            </div>
        </div>

        <div class="divide-y divide-slate-100">
            @forelse($pendingUsers as $pendingUser)
                @php
                    $requestedRoleLabel = $pendingUser->requested_role === 'doctor'
                        ? 'Doctor'
                        : ($pendingUser->requested_role === 'asistente' ? 'Asistente' : 'Sin especificar');
                    $hasValidRequest = in_array($pendingUser->requested_role, ['doctor', 'asistente'], true);
                @endphp
                <article class="grid gap-4 p-4 sm:p-5 lg:grid-cols-[1fr_auto] lg:items-center">
                    <div class="flex min-w-0 items-start gap-3 sm:gap-4">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-[#f6e8ff] text-sm font-bold text-[#9700cf]">{{ mb_strtoupper(mb_substr($pendingUser->name, 0, 1)) }}</span>
                        <div class="min-w-0">
                            <p class="break-words font-bold text-slate-800">{{ $pendingUser->name }}</p>
                            <p class="mt-1 break-all text-sm text-slate-500">{{ $pendingUser->email }}</p>
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700">Pendiente</span>
                                <span class="rounded-full bg-[#f6e8ff] px-3 py-1 text-xs font-bold text-[#9700cf]">Solicitó: {{ $requestedRoleLabel }}</span>
                                <span class="text-xs text-slate-400">{{ $pendingUser->created_at?->diffForHumans() }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="grid w-full grid-cols-1 gap-2 sm:flex sm:flex-wrap lg:w-auto lg:justify-end">
                        @if($hasValidRequest)
                            <form method="POST" action="{{ route('admin.users.approve', $pendingUser) }}" class="w-full sm:w-auto">
                                @csrf
                                @method('PUT')
                                <button class="w-full rounded-xl bg-[#9700cf] px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-[#d3a2f2] transition hover:bg-[#8200b8] sm:w-auto">Aprobar como {{ $requestedRoleLabel }}</button>
                            </form>
                        @endif
                        <a href="{{ route('admin.users.edit', $pendingUser) }}" class="inline-flex w-full items-center justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-600 transition hover:bg-slate-50 sm:w-auto">{{ $hasValidRequest ? 'Revisar datos' : 'Asignar rol' }}</a>
                    </div>
                </article>
            @empty
                <div class="p-8 text-center">
                    <p class="text-sm font-bold text-emerald-700">No hay solicitudes pendientes</p>
                    <p class="mt-1 text-xs text-slate-400">Todas las cuentas han sido revisadas.</p>
                </div>
            @endforelse
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_4px_24px_rgba(30,34,60,.05)]">
        <div class="border-b border-slate-100 p-5">
            <form method="GET" action="{{ route('admin.users') }}" class="flex flex-col gap-3 sm:flex-row">
                <input type="search" name="search" value="{{ $search }}" placeholder="Buscar por nombre, correo o rol" class="min-w-0 flex-1 rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]">
                <button class="rounded-xl bg-[#9700cf] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#8200b8]">Buscar</button>
                @if($search !== '')
                    <a href="{{ route('admin.users') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-5 py-3 text-sm font-bold text-slate-600 transition hover:bg-slate-50">Limpiar</a>
                @endif
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100">
                <thead class="bg-slate-50 text-left text-[10px] font-bold uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-5 py-3">Nombre</th>
                        <th class="px-5 py-3">Correo</th>
                        <th class="px-5 py-3">Rol</th>
                        <th class="px-5 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($users as $user)
                        <tr>
                            <td class="px-5 py-4 font-semibold text-slate-800">{{ $user->name }}</td>
                            <td class="px-5 py-4 text-slate-500">{{ $user->email }}</td>
                            <td class="px-5 py-4">
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase {{ $roleBadges[$user->role] ?? 'bg-slate-100 text-slate-600' }}">{{ $roleOptions[$user->role] ?? 'Sin rol activo' }}</span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('admin.users.edit', $user) }}" class="text-sm font-semibold text-[#9700cf] hover:text-[#8200b8]">Editar</a>
                                    <button type="button" onclick="document.getElementById('delete-user-{{ $user->id }}')?.showModal()" class="text-sm font-semibold text-fuchsia-600 hover:text-fuchsia-700">Eliminar</button>
                                </div>
                                <dialog id="delete-user-{{ $user->id }}" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-3xl p-0 shadow-2xl backdrop:bg-slate-950/50">
                                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="bg-white">
                                        @csrf
                                        @method('DELETE')
                                        <div class="border-b border-slate-100 px-6 py-5">
                                            <h2 class="text-2xl font-bold text-slate-900">Confirmar eliminación</h2>
                                            <p class="mt-2 text-sm text-slate-500">Este usuario ya no podrá acceder al sistema.</p>
                                        </div>
                                        <div class="px-6 py-5">
                                            <div class="rounded-2xl bg-slate-50 p-4 text-left">
                                                <p class="font-bold text-slate-900">{{ $user->name }}</p>
                                                <p class="mt-1 text-sm text-slate-500">{{ $user->email }}</p>
                                            </div>
                                        </div>
                                        <div class="flex justify-end gap-3 border-t border-slate-100 px-6 py-4">
                                            <button type="button" onclick="this.closest('dialog')?.close()" class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-bold text-slate-600">Cancelar</button>
                                            <button class="rounded-xl bg-[#9700cf] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-[#d3a2f2]">Confirmar</button>
                                        </div>
                                    </form>
                                </dialog>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-12 text-center text-sm text-slate-400">No hay usuarios que coincidan con la búsqueda.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-100 px-5 py-4">{{ $users->links() }}</div>
    </section>
</div>
@endsection

