<?php

namespace App\Http\Controllers;

use App\Support\ClinicContext;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\CalendarBlock;
use App\Models\ClinicSetting;
use App\Models\Dentist;
use App\Models\DentistSchedule;
use App\Models\InventoryItem;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\User;
use App\Services\AppointmentCompletionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        if ($request->session()->get('access_role') === 'superadmin') {
            $settings = ClinicSetting::current();
            $roleCounts = User::query()
                ->selectRaw('role, COUNT(*) as total')
                ->groupBy('role')
                ->pluck('total', 'role');
            $databaseStatus = 'Conectada';

            try {
                \DB::connection()->getPdo();
            } catch (\Throwable) {
                $databaseStatus = 'Sin conexion';
            }

            $databasePath = config('database.default') === 'sqlite'
                ? config('database.connections.sqlite.database')
                : null;
            $databaseSize = $databasePath && is_file($databasePath)
                ? round(filesize($databasePath) / 1024 / 1024, 2).' MB'
                : 'Gestionada por '.strtoupper(config('database.default'));

            return view('admin.dashboard', [
                'system' => [
                    'environment' => app()->environment(),
                    'laravelVersion' => app()->version(),
                    'phpVersion' => PHP_VERSION,
                    'database' => $databaseStatus,
                    'databaseDriver' => strtoupper(config('database.default')),
                    'databaseSize' => $databaseSize,
                    'mailDriver' => strtoupper(config('mail.default')),
                    'cacheDriver' => strtoupper(config('cache.default')),
                    'lastUpdate' => $this->latestSystemUpdate(),
                    'status' => $settings->maintenance_mode_enabled ? 'Modo mantenimiento activo' : 'Operando correctamente',
                ],
                'accounts' => [
                    'total' => User::count(),
                    'superadmins' => (int) ($roleCounts['superadmin'] ?? 0),
                    'admins' => (int) ($roleCounts['admin'] ?? 0),
                    'doctors' => (int) ($roleCounts['doctor'] ?? 0),
                    'assistants' => (int) ($roleCounts['asistente'] ?? 0),
                    'pending' => (int) ($roleCounts['pendiente'] ?? 0),
                ],
                'settings' => [
                    'maintenance' => $settings->maintenance_mode_enabled,
                    'audit' => $settings->administrative_audit_enabled,
                    'reminders' => $settings->automatic_reminders_enabled,
                    'inventoryAlerts' => $settings->inventory_alerts_enabled,
                ],
                'recentUsers' => User::latest('updated_at')->limit(5)->get(['id', 'name', 'email', 'role', 'updated_at']),
                'recentAudits' => AuditLog::latest()->limit(6)->get(),
                'operations' => [
                    'auditsToday' => AuditLog::whereDate('created_at', now()->toDateString())->count(),
                    'auditTotal' => AuditLog::count(),
                    'admins' => (int) ($roleCounts['superadmin'] ?? 0),
                    'configurationReady' => filled($settings->clinic_name) && filled($settings->support_email),
                ],
            ]);
        }

        $this->completeOverdueAppointments($request);

        $view = in_array($request->query('view', 'dia'), ['dia', 'semana', 'mes'], true)
            ? $request->query('view', 'dia')
            : 'dia';

        try {
            $selectedDate = $request->filled('date')
                ? now()->parse($request->query('date'))->startOfDay()
                : now()->startOfDay();
        } catch (\Throwable) {
            $selectedDate = now()->startOfDay();
        }

        if ($view === 'mes' && $request->filled('year') && $request->filled('month')) {
            $selectedDate = $selectedDate->setDate(
                max(1970, min(2100, (int) $request->query('year'))),
                max(1, min(12, (int) $request->query('month'))),
                1
            );
        }

        $today = now()->toDateString();
        $role = $request->session()->get('access_role');
        $isDoctor = $role === 'doctor';
        $isOperationalAdmin = $role === 'admin';
        $doctorDentist = $isDoctor ? $this->sessionDentist($request) : null;

        $agendaHoy = $this->scopeAppointmentsForRole(Appointment::with(['patient', 'dentist']), $isDoctor, $doctorDentist)
            ->whereDate('date', $today)
            ->whereNotIn('estado', ['Cancelada', 'Terminada'])
            ->orderBy('time')
            ->get()
            ->map(function (Appointment $appointment) {
                return $this->serializeAppointment($appointment);
            });

        $proximasCitas = $this->scopeAppointmentsForRole(Appointment::with(['patient', 'dentist']), $isDoctor, $doctorDentist)
            ->whereDate('date', '>=', $today)
            ->whereNotIn('estado', ['Cancelada', 'Asistida', 'Terminada'])
            ->orderBy('date')
            ->orderBy('time')
            ->limit(4)
            ->get()
            ->map(function (Appointment $appointment) {
                return $this->serializeAppointment($appointment);
            });

        $tratamientos = $this->scopeTreatmentsForRole(Treatment::query(), $isDoctor, $doctorDentist)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(3)
            ->get()
            ->map(function (Treatment $treatment) {
                return [
                    'tratamiento' => $treatment->tratamiento,
                    'tipo' => $treatment->tipo,
                    'fecha' => $treatment->fecha?->format('d/m/Y'),
                    'estado' => $treatment->estado,
                ];
            });

        $resumen = [
            'citas_hoy' => $agendaHoy->count(),
            'pacientes' => $isDoctor
                ? Patient::whereHas('appointments', fn ($query) => $query->where('dentist_id', $doctorDentist?->id ?? 0))->count()
                : Patient::count(),
            'dentistas' => Dentist::count(),
            'tratamientos_pendientes' => $this->scopeTreatmentsForRole(Treatment::where('estado', 'Pendiente'), $isDoctor, $doctorDentist)->count(),
        ];

        $monthlyRealizedTreatments = $this->scopeTreatmentsForRole(
            Treatment::with('patient')
                ->where('estado', 'Realizado')
                ->whereYear('fecha', now()->year)
                ->whereMonth('fecha', now()->month),
            $isDoctor,
            $doctorDentist
        )
            ->orderByDesc('fecha')
            ->get();

        $resumen['tratamientos_realizados_mes'] = $monthlyRealizedTreatments->count();

        $actividad = [
            'Se actualizaron ' . $this->scopeAppointmentsForRole(Appointment::where('estado', 'Confirmada'), $isDoctor, $doctorDentist)->count() . ' citas confirmadas.',
            'Hay ' . $this->scopeAppointmentsForRole(Appointment::where('estado', 'Pendiente'), $isDoctor, $doctorDentist)->count() . ' citas pendientes de confirmación.',
        ];

        $calendarAppointments = $this->scopeAppointmentsForRole(Appointment::with(['patient', 'dentist']), $isDoctor, $doctorDentist)
            ->whereYear('date', $selectedDate->year)
            ->whereMonth('date', $selectedDate->month)
            ->whereNotIn('estado', ['Cancelada', 'Terminada'])
            ->get()
            ->map(fn (Appointment $appointment) => $this->serializeAppointment($appointment))
            ->values()
            ->toArray();

        $calendarData = $this->buildCalendar($selectedDate->toDateString(), $calendarAppointments);

        $selectedDayEvents = $this->scopeAppointmentsForRole(Appointment::with(['patient', 'dentist']), $isDoctor, $doctorDentist)
            ->whereDate('date', $selectedDate->toDateString())
            ->whereNotIn('estado', ['Cancelada', 'Terminada'])
            ->orderBy('time')
            ->get()
            ->map(fn (Appointment $appointment) => $this->serializeAppointment($appointment));

        $selectedWeekStart = $selectedDate->copy()->startOfWeek();
        $selectedWeekEnd = $selectedWeekStart->copy()->endOfWeek();
        $selectedWeekAppointments = $this->scopeAppointmentsForRole(Appointment::with(['patient', 'dentist']), $isDoctor, $doctorDentist)
            ->whereBetween('date', [$selectedWeekStart->toDateString(), $selectedWeekEnd->toDateString()])
            ->whereNotIn('estado', ['Cancelada', 'Terminada'])
            ->orderBy('date')
            ->orderBy('time')
            ->get()
            ->map(fn (Appointment $appointment) => $this->serializeAppointment($appointment));

        $weekDays = collect(range(0, 6))->map(function (int $offset) use ($selectedWeekStart, $selectedWeekAppointments) {
            $date = $selectedWeekStart->copy()->addDays($offset);

            return [
                'date' => $date,
                'events' => $selectedWeekAppointments
                    ->where('fecha', $date->toDateString())
                    ->values(),
            ];
        });

        $inventoryAlerts = InventoryItem::query()
            ->whereColumn('stock', '<=', 'reposicion')
            ->orderBy('stock')
            ->limit(4)
            ->get();

        $weekStart = now()->startOfWeek();
        $weeklyAppointments = collect(range(0, 6))->map(function (int $offset) use ($weekStart, $isDoctor, $doctorDentist) {
            $date = $weekStart->copy()->addDays($offset);

            return [
                'label' => ucfirst($date->locale('es')->isoFormat('dd')),
                'date' => $date->format('d'),
                'count' => $this->scopeAppointmentsForRole(Appointment::whereDate('date', $date->toDateString()), $isDoctor, $doctorDentist)
                    ->whereNotIn('estado', ['Cancelada', 'Terminada'])
                    ->count(),
                'today' => $date->isToday(),
            ];
        });

        $maxWeeklyAppointments = max(1, (int) $weeklyAppointments->max('count'));
        $appointmentStatus = collect(['Confirmada', 'Pendiente', 'Asistida', 'Terminada', 'Cancelada'])
            ->mapWithKeys(fn (string $status) => [
                $status => $this->scopeAppointmentsForRole(Appointment::where('estado', $status), $isDoctor, $doctorDentist)->count(),
            ]);
        $totalAppointments = max(1, (int) $appointmentStatus->sum());

        return view('dashboard.index', array_merge($calendarData, [
            'resumen' => $resumen,
            'agendaHoy' => $agendaHoy,
            'proximasCitas' => $proximasCitas,
            'tratamientos' => $tratamientos,
            'actividad' => $actividad,
            'inventoryAlerts' => $inventoryAlerts,
            'weeklyAppointments' => $weeklyAppointments,
            'maxWeeklyAppointments' => $maxWeeklyAppointments,
            'appointmentStatus' => $appointmentStatus,
            'totalAppointments' => $totalAppointments,
            'view' => $view,
            'selectedDate' => $selectedDate,
            'selectedDayEvents' => $selectedDayEvents,
            'weekDays' => $weekDays,
            'isOperationalAdmin' => $isOperationalAdmin,
        ]));
    }

    public function adminCalendar(Request $request)
    {
        $this->completeOverdueAppointments($request);

        $selectedDentist = $request->query('dentista', 'Todos');
        $selectedDate = now()->startOfMonth();

        if ($request->filled('year') && $request->filled('month')) {
            $selectedDate = $selectedDate->setDate(
                max(1970, min(2100, (int) $request->query('year'))),
                max(1, min(12, (int) $request->query('month'))),
                1
            );
        }

        $calendarQuery = Appointment::with(['patient', 'dentist'])
            ->whereYear('date', $selectedDate->year)
            ->whereMonth('date', $selectedDate->month)
            ->whereNotIn('estado', ['Cancelada', 'Terminada']);

        if ($selectedDentist !== 'Todos' && $selectedDentist !== '') {
            $calendarQuery->where('dentist_id', $selectedDentist);
        }

        $calendarAppointments = $calendarQuery
            ->orderBy('date')
            ->orderBy('time')
            ->get()
            ->map(fn (Appointment $appointment) => $this->serializeAppointment($appointment))
            ->values()
            ->toArray();

        return view('admin.calendar', array_merge(
            $this->buildCalendar($selectedDate->toDateString(), $calendarAppointments),
            [
                'dentists' => Dentist::orderBy('nombre')->get(['id', 'nombre']),
                'selectedDentist' => $selectedDentist,
                'selectedDate' => $selectedDate,
            ]
        ));
    }

    public function calendar(Request $request)
    {
        $this->completeOverdueAppointments($request);

        $view = in_array($request->query('view', 'mes'), ['dia', 'semana', 'mes']) ? $request->query('view', 'mes') : 'mes';
        try {
            $selectedDate = $request->filled('date')
                ? Carbon::createFromFormat('Y-m-d', $request->query('date'))->startOfDay()
                : now()->startOfDay();
        } catch (\Throwable) {
            $selectedDate = now()->startOfDay();
        }

        if ($view === 'mes' && ($request->filled('year') || $request->filled('month'))) {
            $year = max(1970, min(2100, (int) $request->query('year', $selectedDate->year)));
            $month = max(1, min(12, (int) $request->query('month', $selectedDate->month)));
            $selectedDate->setDate($year, $month, 1);
        }

        $isDoctor = $request->session()->get('access_role') === 'doctor';
        $isOperationalAdmin = $request->session()->get('access_role') === 'admin';
        $isAssistant = in_array($request->session()->get('access_role'), ['asistente', 'admin'], true);
        $doctorDentist = $isDoctor ? $this->sessionDentist($request) : null;
        $agendaMode = $isDoctor && $request->query('agenda') === 'equipo' ? 'equipo' : 'mio';
        $selectedDentist = $isAssistant ? $request->query('dentista', 'Todos') : ($doctorDentist?->id ?? 'Todos');
        $scheduleDentist = $isDoctor
            ? $doctorDentist
            : ($isAssistant && $selectedDentist !== 'Todos' && $selectedDentist !== '' ? Dentist::find($selectedDentist) : null);
        $selectedTreatment = trim((string) $request->query('tratamiento', 'Todos'));

        $appointmentsQuery = Appointment::with(['patient', 'dentist', 'participatingDentists'])
            ->whereNotIn('estado', ['Cancelada', 'Terminada'])
            ->when($isDoctor && $agendaMode === 'mio', function ($query) use ($doctorDentist) {
                $dentistId = $doctorDentist?->id ?? 0;
                $query->where(function ($appointments) use ($dentistId) {
                    $appointments->where('dentist_id', $dentistId)
                        ->orWhereHas('participatingDentists', fn ($participants) => $participants->whereKey($dentistId));
                });
            })
            ->when($isAssistant && $selectedDentist !== 'Todos' && $selectedDentist !== '', fn ($query) => $query->where('dentist_id', $selectedDentist))
            ->when($selectedTreatment !== '' && $selectedTreatment !== 'Todos', fn ($query) => $query->where('tipo', $selectedTreatment));

        $appointments = $appointmentsQuery
            ->get();
        $serializedAppointments = $appointments
            ->map(function (Appointment $appointment) use ($isDoctor, $doctorDentist, $agendaMode) {
                $serialized = $this->serializeAppointment($appointment);
                $collaborators = $appointment->participatingDentists
                    ->filter(fn (Dentist $dentist) => $dentist->pivot?->participation_role === 'collaborator')
                    ->pluck('nombre')
                    ->values();
                $isCollaborative = $collaborators->isNotEmpty();
                $isOwn = $appointment->dentist_id === $doctorDentist?->id
                    || $appointment->participatingDentists->contains('id', $doctorDentist?->id);

                if ($isCollaborative) {
                    $serialized['color'] = '#0f766e';
                    $serialized['dentista'] = collect([$appointment->dentist?->nombre])
                        ->merge($collaborators)
                        ->filter()
                        ->unique()
                        ->join(' + ');
                } elseif ($isDoctor && $agendaMode === 'equipo' && ! $isOwn) {
                    $serialized['color'] = '#94a3b8';
                }

                $serialized['collaborative'] = $isCollaborative;
                $serialized['other_doctor'] = $isDoctor && ! $isOwn;

                return $serialized;
            })
            ->values();
        $calendarData = $this->buildCalendar($selectedDate->toDateString(), $serializedAppointments->toArray());
        $calendarData['todayDate'] = now()->toDateString();

        $today = now();
        $selectedDay = $selectedDate->toDateString();
        $todayEvents = $serializedAppointments
            ->where('fecha', $selectedDay)
            ->values();

        $weekStart = $selectedDate->copy()->startOfWeek();
        $weekEnd = $weekStart->copy()->addDays(6);
        $weekEvents = $serializedAppointments->filter(function ($appointment) use ($weekStart, $weekEnd) {
            $date = $appointment['fecha'];
            return $date >= $weekStart->toDateString() && $date <= $weekEnd->toDateString();
        })->values();

        $blocks = CalendarBlock::with(['dentist', 'targetUser'])
            ->when(! $isOperationalAdmin, fn ($query) => $query->whereNull('target_user_id'))
            ->when($isDoctor && $agendaMode === 'mio', fn ($query) => $query->where('dentist_id', $doctorDentist?->id ?? 0))
            ->when($isAssistant && $selectedDentist !== 'Todos' && $selectedDentist !== '', fn ($query) => $query->where('dentist_id', $selectedDentist))
            ->whereBetween('date', [
                $view === 'mes' ? $selectedDate->copy()->startOfMonth()->toDateString() : $weekStart->toDateString(),
                $view === 'mes' ? $selectedDate->copy()->endOfMonth()->toDateString() : $weekEnd->toDateString(),
            ])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $dayBlocks = $blocks->filter(fn ($block) => $block->date->toDateString() === $selectedDay)->values();
        $timelineHours = $this->buildTimelineHours($selectedDate, $todayEvents, $dayBlocks, $scheduleDentist);
        $monthStart = $selectedDate->copy()->startOfMonth()->toDateString();
        $monthEnd = $selectedDate->copy()->endOfMonth()->toDateString();
        $monthAppointments = $appointments->filter(function ($appointment) use ($monthStart, $monthEnd) {
            $date = $appointment->date?->toDateString();

            return $date >= $monthStart && $date <= $monthEnd;
        });
        $statusChart = collect(['Pendiente', 'Confirmada', 'Asistida'])->map(function (string $status) use ($monthAppointments) {
            return [
                'label' => $status,
                'value' => $monthAppointments->where('estado', $status)->count(),
            ];
        })->values();
        $topTreatmentChart = $monthAppointments
            ->groupBy('tipo')
            ->map(fn ($items, $name) => ['label' => $name ?: 'Sin tratamiento', 'value' => $items->count()])
            ->sortByDesc('value')
            ->take(5)
            ->values();
        $weekDays = collect(range(0, 6))->map(function (int $offset) use ($weekStart, $weekEvents, $blocks, $scheduleDentist) {
            $date = $weekStart->copy()->addDays($offset);
            $dayBlocks = $blocks->filter(fn ($block) => $block->date->toDateString() === $date->toDateString())->values();
            $scheduleWindow = $this->scheduleWindowForDate($scheduleDentist, $date);
            $hasFullDayBlock = $dayBlocks->contains(fn ($block) => date('H:i', strtotime($block->start_time)) === '00:00' && date('H:i', strtotime($block->end_time)) === '23:59');

            return [
                'date' => $date,
                'events' => $weekEvents->where('fecha', $date->toDateString())->values(),
                'blocks' => $dayBlocks,
                'available_slot' => $scheduleWindow && ! $hasFullDayBlock && $date->copy()->setTimeFromTimeString($scheduleWindow['start'])->greaterThanOrEqualTo(now()->startOfMinute()),
                'available_start' => $scheduleWindow['start'] ?? null,
                'available_end' => $scheduleWindow['slot_end'] ?? null,
            ];
        });

        return view('citas.calendario', array_merge($calendarData, [
            'citas' => $serializedAppointments->toArray(),
            'view' => $view,
            'today' => $today,
            'todayEvents' => $todayEvents,
            'weekEvents' => $weekEvents,
            'selectedDate' => $selectedDate,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'weekDays' => $weekDays,
            'timelineHours' => $timelineHours,
            'statusChart' => $statusChart,
            'topTreatmentChart' => $topTreatmentChart,
            'monthAppointmentsCount' => $monthAppointments->count(),
            'calendarBlocks' => $blocks,
            'dayBlocks' => $dayBlocks,
            'dentists' => Dentist::orderBy('nombre')->get(['id', 'nombre']),
            'assistants' => ($isOperationalAdmin || $isDoctor || $isAssistant)
                ? app(ClinicContext::class)->assistants()->orderBy('name')->get(['id', 'name'])
                : collect(),
            'treatments' => Treatment::whereNull('appointment_id')->whereNull('patient_id')->orderBy('tratamiento')->pluck('tratamiento')->unique()->values(),
            'selectedDentist' => $selectedDentist,
            'selectedTreatment' => $selectedTreatment,
            'isDoctor' => $isDoctor,
            'isAssistant' => $isAssistant,
            'isOperationalAdmin' => $isOperationalAdmin,
            'doctorDentist' => $doctorDentist,
            'agendaMode' => $agendaMode,
        ]));
    }

    public function storeCalendarBlock(Request $request)
    {
        $role = $request->session()->get('access_role');
        $isDoctor = $role === 'doctor';
        $isOperationalAdmin = $role === 'admin';
        $doctorDentist = $isDoctor ? $this->sessionDentist($request) : null;

        $data = $request->validate([
            'target' => $isOperationalAdmin ? ['required', 'string', 'regex:/^(dentist|assistant):[0-9]+$/'] : ['nullable'],
            'dentist_id' => ($isDoctor || $isOperationalAdmin) ? 'nullable' : ['required', app(ClinicContext::class)->exists('dentists', 'id')],
            'date' => 'required|date|after_or_equal:today',
            'type' => 'required|string|max:60',
            'notes' => 'nullable|string|max:500',
        ]);

        $dentistId = $isDoctor ? $doctorDentist?->id : ($isOperationalAdmin ? null : (int) $data['dentist_id']);
        $targetUserId = null;

        if ($isOperationalAdmin) {
            [$targetType, $targetId] = explode(':', $data['target'], 2);

            if ($targetType === 'dentist') {
                $dentistId = Dentist::whereKey($targetId)->value('id');
                abort_unless($dentistId, 422);
            } else {
                $targetUserId = app(ClinicContext::class)->assistants()->whereKey($targetId)->value('id');
                abort_unless($targetUserId, 422);
            }
        }

        abort_unless($dentistId || $targetUserId, 403);
        $startTime = '00:00';
        $endTime = '23:59';

        if ($dentistId && ($conflictingAppointment = $this->findAppointmentOverlappingBlock($dentistId, $data['date'], $startTime, $endTime))) {
            return back()
                ->withErrors(['date' => "Ese día ya tiene la cita #{$conflictingAppointment->id}. Cancela o reprograma las citas antes de marcarlo como no disponible."])
                ->withInput();
        }

        $conflictingBlock = $dentistId
            ? $this->findCalendarBlockOverlap($dentistId, $data['date'], $startTime, $endTime)
            : CalendarBlock::where('target_user_id', $targetUserId)->whereDate('date', $data['date'])->first();

        if ($conflictingBlock) {
            return back()
                ->withErrors(['date' => "Ese día ya está marcado como {$conflictingBlock->type}."])
                ->withInput();
        }

        CalendarBlock::create([
            'dentist_id' => $dentistId,
            'target_user_id' => $targetUserId,
            'created_by_user_id' => $request->session()->get('access_user_id'),
            'date' => $data['date'],
            'start_time' => $startTime,
            'end_time' => $endTime,
            'type' => $data['type'],
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Día marcado como no disponible correctamente.');
    }

    public function destroyCalendarBlock(Request $request, CalendarBlock $block)
    {
        if ($request->session()->get('access_role') === 'doctor') {
            abort_unless($block->dentist_id === $this->sessionDentist($request)?->id, 403);
        }

        $block->delete();

        return back()->with('success', 'Bloqueo eliminado correctamente.');
    }

    private function findAppointmentOverlappingBlock(int $dentistId, string $date, string $startTime, string $endTime): ?Appointment
    {
        $requestedStart = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$startTime}");
        $requestedEnd = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$endTime}");

        return Appointment::where('dentist_id', $dentistId)
            ->whereDate('date', $date)
            ->whereNotIn('estado', ['Cancelada', 'Terminada'])
            ->orderBy('time')
            ->get()
            ->first(function (Appointment $appointment) use ($requestedStart, $requestedEnd) {
                $appointmentStart = Carbon::parse($appointment->date->toDateString().' '.$appointment->time);
                $appointmentEnd = $appointment->end_time
                    ? Carbon::parse($appointment->date->toDateString().' '.$appointment->end_time)
                    : $appointmentStart->copy()->addHour();

                return $requestedStart->lt($appointmentEnd) && $requestedEnd->gt($appointmentStart);
            });
    }

    private function findCalendarBlockOverlap(int $dentistId, string $date, string $startTime, string $endTime): ?CalendarBlock
    {
        $requestedStart = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$startTime}");
        $requestedEnd = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$endTime}");

        return CalendarBlock::where('dentist_id', $dentistId)
            ->whereDate('date', $date)
            ->orderBy('start_time')
            ->get()
            ->first(function (CalendarBlock $block) use ($requestedStart, $requestedEnd) {
                $blockStart = Carbon::parse($block->date->toDateString().' '.$block->start_time);
                $blockEnd = Carbon::parse($block->date->toDateString().' '.$block->end_time);

                return $requestedStart->lt($blockEnd) && $requestedEnd->gt($blockStart);
            });
    }

    private function sessionDentist(Request $request): ?Dentist
    {
        $user = User::find($request->session()->get('access_user_id'))
            ?? User::where('email', $request->session()->get('access_email'))->first();

        if (! $user) {
            return null;
        }

        return Dentist::where('user_id', $user->id)->first()
            ?? Dentist::whereNull('user_id')->where('nombre', $user->name)->first();
    }

    private function completeOverdueAppointments(Request $request): void
    {
        $isDoctor = $request->session()->get('access_role') === 'doctor';
        $doctorDentist = $isDoctor ? $this->sessionDentist($request) : null;

        app(AppointmentCompletionService::class)->completeOverdue($doctorDentist?->id);
    }

    private function scopeAppointmentsForRole($query, bool $isDoctor, ?Dentist $dentist)
    {
        if ($isDoctor) {
            $dentistId = $dentist?->id ?? 0;
            $query->where(function ($appointments) use ($dentistId) {
                $appointments->where('dentist_id', $dentistId)
                    ->orWhereHas('participatingDentists', fn ($participants) => $participants->whereKey($dentistId));
            });
        }

        return $query;
    }

    private function scopeTreatmentsForRole($query, bool $isDoctor, ?Dentist $dentist)
    {
        if ($isDoctor) {
            $dentistId = $dentist?->id ?? 0;
            $query->whereHas('appointment', function ($appointmentQuery) use ($dentistId) {
                $appointmentQuery->where(function ($appointments) use ($dentistId) {
                    $appointments->where('dentist_id', $dentistId)
                        ->orWhereHas('participatingDentists', fn ($participants) => $participants->whereKey($dentistId));
                });
            });
        }

        return $query;
    }

    private function currentAssistantUserId(): ?int
    {
        $userId = session('access_user_id');

        if (session('access_role') !== 'asistente' || ! $userId) {
            return null;
        }

        return app(ClinicContext::class)->assistants()->whereKey($userId)->exists()
            ? (int) $userId
            : null;
    }

    private function latestSystemUpdate(): string
    {
        $latest = collect([
            Appointment::max('updated_at'),
            Patient::max('updated_at'),
            Dentist::max('updated_at'),
            Treatment::max('updated_at'),
            InventoryItem::max('updated_at'),
            User::max('updated_at'),
            ClinicSetting::max('updated_at'),
        ])->filter()->max();

        return $latest
            ? 'Ultima actualizacion: '.date('d/m/Y H:i', strtotime($latest))
            : 'Sin actividad registrada';
    }

    private function serializeAppointment(Appointment $appointment): array
    {
        $endTime = $appointment->end_time ?: $appointment->time;

        return [
            'id' => $appointment->id,
            'paciente' => $appointment->patient?->nombre ?? $appointment->paciente,
            'dentista' => $appointment->dentist?->nombre ?? $appointment->dentista,
            'fecha' => $appointment->date?->toDateString() ?? $appointment->date,
            'hora' => $appointment->time,
            'hora_fin' => $endTime,
            'horario' => date('H:i', strtotime($appointment->time)).' - '.date('H:i', strtotime($endTime)),
            'tipo' => $appointment->tipo,
            'color' => $this->treatmentColor($appointment->tipo, $appointment->color),
            'estado' => $appointment->estado,
            'notas' => $appointment->notas,
            'reminder_sent' => (bool) $appointment->reminder_sent,
            'show_conflict_alert' => false,
        ];
    }

    private function buildTimelineHours(Carbon $date, $events, $blocks, ?Dentist $dentist = null)
    {
        $scheduleWindow = $this->scheduleWindowForDate($dentist, $date);

        if ($scheduleWindow) {
            $start = $date->copy()->setTimeFromTimeString($scheduleWindow['start']);
            $end = $date->copy()->setTimeFromTimeString($scheduleWindow['end']);
        } else {
            $start = $date->copy()->setTime(7, 0);
            $end = $date->copy()->setTime(21, 0);
        }

        $slots = collect();
        for ($slotStart = $start->copy(); $slotStart->lt($end); $slotStart->addHour()) {
            $slots->push($slotStart->copy());
        }

        return $slots->map(function (Carbon $slotStart) use ($date, $events, $blocks, $scheduleWindow, $end) {
            $slotEnd = $slotStart->copy()->addHour();
            if ($slotEnd->gt($end)) {
                $slotEnd = $end->copy();
            }
            $hourEvents = collect($events)->filter(fn ($event) => (int) date('G', strtotime($event['hora'])) === (int) $slotStart->format('G'))->values();
            $hourBlocks = collect($blocks)->filter(function ($block) use ($slotStart, $slotEnd) {
                $blockStart = Carbon::parse($block->date->toDateString().' '.$block->start_time);
                $blockEnd = Carbon::parse($block->date->toDateString().' '.$block->end_time);
                return $blockStart->lt($slotEnd) && $blockEnd->gt($slotStart);
            })->values();

            $isFree = (bool) $scheduleWindow
                && $hourEvents->isEmpty()
                && $hourBlocks->isEmpty()
                && $slotStart->greaterThanOrEqualTo(now()->startOfMinute());

            return [
                'hour' => (int) $slotStart->format('G'),
                'time' => $slotStart->format('H:i'),
                'end_time' => $slotEnd->format('H:i'),
                'date' => $date->toDateString(),
                'events' => $hourEvents,
                'blocks' => $hourBlocks,
                'free' => $isFree,
                'create_url' => route('citas.create', [
                    'fecha' => $date->toDateString(),
                    'hora' => $slotStart->format('H:i'),
                    'hora_fin' => $slotStart->copy()->addHour()->format('H:i'),
                ]),
            ];
        });
    }

    private function scheduleWindowForDate(?Dentist $dentist, Carbon $date): ?array
    {
        if (! $dentist) {
            return null;
        }

        $weekday = $date->isoWeekday();
        $schedule = DentistSchedule::firstOrCreate(
            ['dentist_id' => $dentist->id, 'weekday' => $weekday],
            [
                'enabled' => $weekday <= 5,
                'start_time' => '09:00',
                'end_time' => $weekday === 5 ? '15:00' : '17:00',
            ]
        );

        if (! $schedule->enabled) {
            return null;
        }

        $start = date('H:i', strtotime($schedule->start_time));
        $end = date('H:i', strtotime($schedule->end_time));
        $slotEnd = Carbon::parse($date->toDateString().' '.$start)->addHour();
        $scheduleEnd = Carbon::parse($date->toDateString().' '.$end);

        if ($slotEnd->gt($scheduleEnd)) {
            $slotEnd = $scheduleEnd;
        }

        return [
            'start' => $start,
            'end' => $end,
            'slot_end' => $slotEnd->format('H:i'),
        ];
    }

    private function treatmentColor(string $treatmentName, ?string $fallback = null): string
    {
        return Treatment::whereNull('appointment_id')
            ->whereNull('patient_id')
            ->where('tratamiento', $treatmentName)
            ->value('color')
            ?: ($fallback ?: '#9700cf');
    }

    private function buildCalendar(string $referenceDate, array $appointments = []): array
    {
        $today = new \DateTimeImmutable($referenceDate);
        $monthStart = new \DateTimeImmutable($today->format('Y-m-01'));
        $daysInMonth = (int) $monthStart->format('t');
        $startWeekday = (int) $monthStart->format('N');

        $calendarDays = [];
        for ($slot = 1; $slot <= $startWeekday - 1 + $daysInMonth; $slot++) {
            if ($slot < $startWeekday) {
                $calendarDays[] = ['day' => null, 'date' => null, 'events' => []];
                continue;
            }

            $dayNumber = $slot - $startWeekday + 1;
            $date = $monthStart->modify('+' . ($dayNumber - 1) . ' days');
            $dateKey = $date->format('Y-m-d');

            $events = array_values(array_filter($appointments, function ($item) use ($dateKey) {
                $eventDate = $item['fecha'] ?? $item['date'] ?? null;

                return $eventDate && date('Y-m-d', strtotime($eventDate)) === $dateKey;
            }));

            $calendarDays[] = [
                'day' => $dayNumber,
                'date' => $dateKey,
                'events' => $events,
            ];
        }

        while (count($calendarDays) % 7 !== 0) {
            $calendarDays[] = ['day' => null, 'date' => null, 'events' => []];
        }

        $spanishMonths = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

        return [
            'calendarDays' => $calendarDays,
            'monthName' => $spanishMonths[(int) $monthStart->format('n') - 1],
            'todayDate' => $referenceDate,
            'month' => (int) $monthStart->format('n'),
            'year' => (int) $monthStart->format('Y'),
            'prevMonth' => $monthStart->modify('-1 month'),
            'nextMonth' => $monthStart->modify('+1 month'),
        ];
    }
}
