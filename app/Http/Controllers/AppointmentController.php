<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\CalendarBlock;
use App\Models\ClinicSetting;
use App\Models\Dentist;
use App\Models\DentistSchedule;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\User;
use App\Services\AppointmentCompletionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AppointmentController extends Controller
{
    private const VALID_STATUSES = ['Pendiente', 'Confirmada', 'Cancelada', 'Asistida', 'Terminada'];
    private const CUSTOM_TREATMENT_OPTION = '__custom_treatment__';
    private const QUICK_PATIENT_OPTION = '__quick_patient__';

    public function index(Request $request)
    {
        $currentDentist = $this->currentDoctorDentist();
        app(AppointmentCompletionService::class)->completeOverdue($currentDentist?->id);

        $search = trim(strtolower($request->query('search', '')));
        $estado = trim($request->query('estado', 'Todos'));
        if (in_array($estado, ['Cancelada', 'Terminada'], true)) {
            $estado = 'Todos';
        }
        $dentista = $currentDentist ? 'Todos' : trim($request->query('dentista', 'Todos'));
        $fecha = trim($request->query('fecha', ''));
        $order = $request->query('order', 'fecha_asc');
        if ($currentDentist && $order === 'dentista_asc') {
            $order = 'fecha_asc';
        }

        $query = $this->scopeAppointmentsForCurrentUser(Appointment::with(['patient', 'dentist', 'participatingDentists']));

        if ($currentDentist) {
            $this->scopeForDentistParticipation($query, $currentDentist->id);
        }

        if ($search !== '') {
            $query->where(function ($subQuery) use ($search) {
                $subQuery->whereHas('patient', fn($q) => $q->where('nombre', 'like', "%{$search}%"))
                    ->orWhereHas('dentist', fn($q) => $q->where('nombre', 'like', "%{$search}%"))
                    ->orWhere('tipo', 'like', "%{$search}%");
            });
        }

        if ($estado !== '' && $estado !== 'Todos') {
            $query->where('estado', $estado);
        } else {
            $query->whereNotIn('estado', ['Cancelada', 'Terminada']);
        }

        if ($dentista !== '' && $dentista !== 'Todos') {
            $query->whereHas('dentist', fn($q) => $q->where('nombre', $dentista));
        }

        if ($fecha !== '') {
            $query->whereDate('date', $fecha);
        }

        $query = match ($order) {
            'fecha_desc' => $query->orderByDesc('date')->orderByDesc('time'),
            'paciente_asc' => $query->join('patients', 'appointments.patient_id', '=', 'patients.id')->orderBy('patients.nombre')->select('appointments.*'),
            'dentista_asc' => $query->join('dentists', 'appointments.dentist_id', '=', 'dentists.id')->orderBy('dentists.nombre')->select('appointments.*'),
            default => $query->orderBy('date')->orderBy('time'),
        };

        $appointments = $query->get()->map(function (Appointment $appointment) {
            return $this->serializeAppointment($appointment);
        });

        $filtered = $appointments;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 6;
        $currentItems = $filtered->slice(($currentPage - 1) * $perPage, $perPage)->values()->all();
        $citasPaginator = new LengthAwarePaginator($currentItems, $filtered->count(), $perPage, $currentPage, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        $baseCitas = $this->scopeAppointmentsForCurrentUser(Appointment::query())
            ->when($currentDentist, fn ($query) => $this->scopeForDentistParticipation($query, $currentDentist->id))
            ->get();
        $today = now()->toDateString();

        $resumen = [
            'hoy' => $baseCitas->where('date', $today)->count(),
            'confirmadas' => $baseCitas->where('estado', 'Confirmada')->count(),
            'pendientes' => $baseCitas->where('estado', 'Pendiente')->count(),
            'proximas' => $baseCitas->where('date', '>=', $today)->whereNotIn('estado', ['Cancelada', 'Asistida', 'Terminada'])->count(),
        ];

        $estados = Appointment::select('estado')->distinct()->pluck('estado')->sort()->values();
        $dentistas = Dentist::query()
            ->when($currentDentist, fn ($query) => $query->whereKey($currentDentist->id))
            ->select('nombre')
            ->distinct()
            ->pluck('nombre')
            ->sort()
            ->values();

        return view('citas.index', [
            'citas' => $citasPaginator,
            'resumen' => $resumen,
            'estados' => $estados,
            'dentistas' => $dentistas,
            'catalogTreatments' => $this->catalogTreatments(),
        ]);
    }

    public function create(Request $request)
    {
        $pacientes = Patient::orderBy('nombre')->pluck('nombre');
        $dentistas = Dentist::orderBy('nombre')->pluck('nombre');
        $tratamientos = Treatment::orderBy('tratamiento')
            ->whereNull('appointment_id')
            ->whereNull('patient_id')
            ->get(['tratamiento', 'tipo', 'costo', 'color'])
            ->unique('tratamiento')
            ->values();
        $currentDentist = $this->currentDoctorDentist();
        $defaultStart = now()->startOfMinute();
        if ($request->filled('fecha') && $request->filled('hora')) {
            try {
                $defaultStart = Carbon::createFromFormat('Y-m-d H:i', $request->query('fecha').' '.$request->query('hora'));
            } catch (\Throwable) {
                $defaultStart = now()->startOfMinute();
            }
        }
        $defaultEnd = $defaultStart->copy()->addHour();
        if ($request->filled('hora_fin')) {
            try {
                $defaultEnd = Carbon::createFromFormat('Y-m-d H:i', $defaultStart->toDateString().' '.$request->query('hora_fin'));
            } catch (\Throwable) {
                $defaultEnd = $defaultStart->copy()->addHour();
            }
        }

        return view('citas.crear', [
            'pacientes' => $pacientes,
            'dentistas' => $dentistas,
            'tratamientos' => $tratamientos,
            'currentDentist' => $currentDentist,
            'collaboratorDentists' => Dentist::query()
                ->when($currentDentist, fn ($query) => $query->whereKeyNot($currentDentist->id))
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'especialidad']),
            'defaultDate' => $defaultStart->toDateString(),
            'defaultStartTime' => $defaultStart->format('H:i'),
            'defaultEndTime' => $defaultEnd->format('H:i'),
            'appointmentDuration' => 60,
        ]);
    }

    public function history(Request $request)
    {
        $currentDentist = $this->currentDoctorDentist();
        $search = trim($request->query('search', ''));
        $estado = $request->query('estado', 'Todos');

        $appointments = $this->scopeAppointmentsForCurrentUser(Appointment::with(['patient', 'dentist', 'participatingDentists']))
            ->when($currentDentist, fn ($query) => $this->scopeForDentistParticipation($query, $currentDentist->id))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->whereHas('patient', fn ($patientQuery) => $patientQuery->where('nombre', 'like', "%{$search}%"))
                        ->orWhereHas('dentist', fn ($dentistQuery) => $dentistQuery->where('nombre', 'like', "%{$search}%"))
                        ->orWhere('tipo', 'like', "%{$search}%");
                });
            })
            ->when(
                $estado !== 'Todos',
                fn ($query) => $query->where('estado', $estado),
                fn ($query) => $query->whereIn('estado', ['Cancelada', 'Terminada'])
            )
            ->orderByDesc('date')
            ->orderByDesc('time')
            ->paginate(10)
            ->withQueryString();

        return view('citas.historial', [
            'citas' => $appointments,
            'estado' => $estado,
            'search' => $search,
        ]);
    }

    public function restore($id)
    {
        $appointment = Appointment::with(['patient', 'dentist', 'participatingDentists'])->findOrFail($id);
        $this->authorizeAppointmentAccess($appointment);

        if ($appointment->estado !== 'Cancelada') {
            return redirect()->route('citas.historial')
                ->withErrors(['estado' => 'Solo se pueden restaurar citas canceladas.']);
        }

        $start = Carbon::parse($appointment->date->toDateString().' '.$appointment->time);
        $end = $this->appointmentEnd($appointment);

        if ($start->lessThan(now()) || $this->findConflictingAppointment(
            $appointment->dentist,
            $appointment->date->toDateString(),
            $start->format('H:i:s'),
            $end->format('H:i:s'),
            $appointment->id
        ) || $this->findPatientConflictingAppointment(
            $appointment->patient,
            $appointment->date->toDateString(),
            $start->format('H:i:s'),
            $end->format('H:i:s'),
            $appointment->id
        )) {
            return redirect()->route('citas.edit', ['id' => $appointment->id])
                ->with('warning', 'La cita no puede recuperarse en su horario anterior. Elige una nueva fecha u hora para reprogramarla.');
        }

        $appointment->update(['estado' => 'Pendiente']);

        return redirect()->route('citas.historial')
            ->with('success', "Cita #{$appointment->id} restaurada como pendiente.");
    }

    public function store(Request $request)
    {
        $currentDentist = $this->currentDoctorDentist();

        $data = $request->validate([
            'paciente' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value !== self::QUICK_PATIENT_OPTION && ! Patient::where('nombre', $value)->exists()) {
                        $fail('El paciente seleccionado no existe.');
                    }
                },
            ],
            'nuevo_paciente_nombre' => 'required_if:paciente,'.self::QUICK_PATIENT_OPTION.'|nullable|string|max:255|regex:/^[\pL\s]+$/u',
            'nuevo_paciente_telefono' => 'required_if:paciente,'.self::QUICK_PATIENT_OPTION.'|nullable|digits:10',
            'dentista' => $currentDentist ? 'nullable' : 'required|string|exists:dentists,nombre',
            'fecha' => 'required|date|after_or_equal:today',
            'hora' => 'required|date_format:H:i',
            'hora_fin' => 'nullable|date_format:H:i',
            'tipo' => 'required|string|max:255',
            'tratamiento_emergencia' => 'nullable|string|max:255',
            'costo_emergencia' => 'nullable|numeric|min:0',
            'collaborator_dentist_id' => 'nullable|integer|exists:dentists,id',
        ], $this->appointmentValidationMessages(), $this->appointmentValidationAttributes());

        $treatmentName = $this->resolveAppointmentTreatmentName($data['tipo'], $data['tratamiento_emergencia'] ?? null, $data['costo_emergencia'] ?? null);
        $isQuickPatient = $data['paciente'] === self::QUICK_PATIENT_OPTION;
        if ($isQuickPatient && Patient::where('nombre', trim($data['nuevo_paciente_nombre']))->exists()) {
            return back()->withErrors([
                'nuevo_paciente_nombre' => 'Ya existe un paciente con ese nombre. Seleccionalo en la lista para evitar duplicados.',
            ])->withInput();
        }
        $patient = $isQuickPatient ? null : $this->resolvePatient($data['paciente']);
        $dentist = $currentDentist ?? $this->resolveDentist($data['dentista']);
        $collaborator = ! empty($data['collaborator_dentist_id'])
            ? Dentist::findOrFail($data['collaborator_dentist_id'])
            : null;
        if ($collaborator?->is($dentist)) {
            return back()->withErrors(['collaborator_dentist_id' => 'El doctor colaborador debe ser diferente al doctor principal.'])->withInput();
        }
        [$time, $endTime] = $this->appointmentTimeRange($data['fecha'], $data['hora'], $data['hora_fin'] ?? null);
        $this->ensureAppointmentIsSchedulable($dentist, $data['fecha'], $time, $endTime);
        if ($collaborator) {
            $this->ensureAppointmentIsSchedulable($collaborator, $data['fecha'], $time, $endTime);
        }

        foreach (array_filter([$dentist, $collaborator]) as $participant) {
            if ($conflictingAppointment = $this->findConflictingAppointment($participant, $data['fecha'], $time, $endTime)) {
                return back()
                    ->withErrors(['hora' => $this->conflictMessage($conflictingAppointment, $participant)])
                    ->withInput();
            }
        }

        if ($patient && ($conflictingAppointment = $this->findPatientConflictingAppointment($patient, $data['fecha'], $time, $endTime))) {
            return back()
                ->withErrors(['hora' => $this->patientConflictMessage($conflictingAppointment)])
                ->withInput();
        }

        foreach (array_filter([$dentist, $collaborator]) as $participant) {
            if ($conflictingBlock = $this->findConflictingCalendarBlock($participant, $data['fecha'], $time, $endTime)) {
                return back()
                    ->withErrors(['hora' => $this->calendarBlockConflictMessage($conflictingBlock).' Doctor: '.$participant->nombre.'.'])
                    ->withInput();
            }
        }

        DB::transaction(function () use (&$patient, $isQuickPatient, $data, $dentist, $collaborator, $time, $endTime, $treatmentName): void {
            if ($isQuickPatient) {
                $patient = Patient::create([
                    'nombre' => trim($data['nuevo_paciente_nombre']),
                    'telefono' => $data['nuevo_paciente_telefono'],
                    'estado' => 'Activo',
                ]);
            }

            $appointment = Appointment::create([
                'patient_id' => $patient->id,
                'dentist_id' => $dentist->id,
                'created_by_user_id' => session('access_user_id'),
                'date' => $data['fecha'],
                'time' => $time,
                'end_time' => $endTime,
                'tipo' => $treatmentName,
                'estado' => ClinicSetting::current()->default_appointment_status,
                'color' => $this->treatmentColor($treatmentName),
            ]);
            $this->syncAppointmentParticipants($appointment, $dentist, $collaborator);
        });

        return redirect()->route('citas')->with('success', $collaborator
            ? 'Cita colaborativa creada correctamente en las agendas de ambos doctores.'
            : 'Cita creada correctamente.');
    }

    public function edit($id)
    {
        $appointment = Appointment::findOrFail($id);
        $this->authorizeAppointmentAccess($appointment);
        $pacientes = Patient::orderBy('nombre')->pluck('nombre');
        $dentistas = Dentist::orderBy('nombre')->pluck('nombre');
        $currentDentist = $this->currentDoctorDentist();
        $catalogTreatments = Treatment::whereNull('appointment_id')
            ->whereNull('patient_id')
            ->orderBy('tratamiento')
            ->get(['tratamiento', 'tipo', 'costo', 'color'])
            ->unique('tratamiento')
            ->values();
        $catalogTreatment = $catalogTreatments->firstWhere('tratamiento', $appointment->tipo);

        return view('citas.editar', [
            'cita' => $this->serializeAppointment($appointment),
            'pacientes' => $pacientes,
            'dentistas' => $dentistas,
            'currentDentist' => $currentDentist,
            'collaboratorDentists' => Dentist::query()
                ->whereKeyNot($currentDentist?->id ?? $appointment->dentist_id)
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'especialidad']),
            'selectedCollaboratorId' => $appointment->collaborators()->value('dentists.id'),
            'baseCost' => (float) ($catalogTreatment?->costo ?? 0),
            'catalogTreatments' => $catalogTreatments,
            'backUrl' => $this->resolveBackUrl(request(), $appointment),
        ]);
    }

    public function update(Request $request, $id)
    {
        $appointment = Appointment::findOrFail($id);
        $this->authorizeAppointmentAccess($appointment);
        $currentDentist = $this->currentDoctorDentist();
        $isHistorical = $appointment->estado === 'Terminada'
            || Treatment::where('appointment_id', $appointment->id)->where('estado', 'Realizado')->exists();

        $data = $request->validate([
            'paciente' => 'required|string|exists:patients,nombre',
            'dentista' => $currentDentist ? 'nullable' : 'required|string|exists:dentists,nombre',
            'fecha' => $isHistorical ? 'required|date' : 'required|date|after_or_equal:today',
            'hora' => 'required|date_format:H:i',
            'hora_fin' => 'nullable|date_format:H:i',
            'tipo' => 'required|string|exists:treatments,tratamiento',
            'estado' => 'required|string|in:'.implode(',', self::VALID_STATUSES),
            'descuento' => 'nullable|numeric|min:0|max:100',
            'procedimiento_extra' => 'nullable',
            'procedimiento_extra.*' => 'nullable|string|max:255',
            'costo_extra' => 'nullable',
            'costo_extra.*' => 'nullable|numeric|min:0',
            'collaborator_dentist_id' => 'nullable|integer|exists:dentists,id',
        ], $this->appointmentValidationMessages(), $this->appointmentValidationAttributes());

        $patient = $this->resolvePatient($data['paciente']);
        $dentist = $currentDentist ?? $this->resolveDentist($data['dentista']);
        $collaborator = ! empty($data['collaborator_dentist_id'])
            ? Dentist::findOrFail($data['collaborator_dentist_id'])
            : null;
        if ($collaborator?->is($dentist)) {
            return back()->withErrors(['collaborator_dentist_id' => 'El doctor colaborador debe ser diferente al doctor principal.'])->withInput();
        }
        $catalogTreatment = Treatment::whereNull('appointment_id')
            ->whereNull('patient_id')
            ->where('tratamiento', $data['tipo'])
            ->first();
        $baseCost = (float) ($catalogTreatment?->costo ?? 0);
        [$extraProcedure, $extraCost] = $this->normalizeExtraTreatments(
            $request->input('procedimiento_extra', []),
            $request->input('costo_extra', [])
        );
        $discount = (float) ($data['descuento'] ?? 0);
        [$time, $endTime] = $this->appointmentTimeRange($data['fecha'], $data['hora'], $data['hora_fin'] ?? null);
        if (! $isHistorical) {
            $this->ensureAppointmentIsSchedulable($dentist, $data['fecha'], $time, $endTime);
            if ($collaborator) {
                $this->ensureAppointmentIsSchedulable($collaborator, $data['fecha'], $time, $endTime);
            }
        }

        foreach (array_filter([$dentist, $collaborator]) as $participant) {
            if (! $isHistorical && $conflictingAppointment = $this->findConflictingAppointment($participant, $data['fecha'], $time, $endTime, $appointment->id)) {
                return back()
                    ->withErrors(['hora' => $this->conflictMessage($conflictingAppointment, $participant)])
                    ->withInput();
            }
        }

        if (! $isHistorical && $conflictingAppointment = $this->findPatientConflictingAppointment($patient, $data['fecha'], $time, $endTime, $appointment->id)) {
            return back()
                ->withErrors(['hora' => $this->patientConflictMessage($conflictingAppointment)])
                ->withInput();
        }

        foreach (array_filter([$dentist, $collaborator]) as $participant) {
            if (! $isHistorical && $conflictingBlock = $this->findConflictingCalendarBlock($participant, $data['fecha'], $time, $endTime)) {
                return back()
                    ->withErrors(['hora' => $this->calendarBlockConflictMessage($conflictingBlock).' Doctor: '.$participant->nombre.'.'])
                    ->withInput();
            }
        }

        $appointment->update([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'date' => $data['fecha'],
            'time' => $time,
            'end_time' => $endTime,
            'tipo' => $data['tipo'],
            'estado' => $data['estado'],
            'color' => $this->treatmentColor($data['tipo'], $appointment->color),
            'descuento' => $discount,
            'procedimiento_extra' => $extraProcedure,
            'costo_extra' => $extraCost,
        ]);
        $this->syncAppointmentParticipants($appointment, $dentist, $collaborator);

        if ($isHistorical && $completedTreatment = Treatment::where('appointment_id', $appointment->id)->first()) {
            $descriptionParts = array_filter([
                $catalogTreatment?->descripcion,
                $extraProcedure ? 'Procedimiento extra: '.$extraProcedure : null,
                $discount > 0 ? 'Descuento aplicado: '.number_format($discount, 2).'%' : null,
            ]);
            $completedTreatment->update([
                'patient_id' => $patient->id,
                'paciente' => $patient->nombre,
                'tratamiento' => $this->displayTreatmentName($data['tipo'], $extraProcedure),
                'tipo' => $catalogTreatment?->tipo ?? $completedTreatment->tipo,
                'fecha' => $data['fecha'],
                'costo' => max(0, ($baseCost + $extraCost) * (1 - ($discount / 100))),
                'descripcion' => implode(' | ', $descriptionParts) ?: null,
            ]);
        }

        return redirect($this->resolveBackUrl($request, $appointment))
            ->with('success', 'Cita actualizada correctamente.');
    }

    public function confirm($id)
    {
        return $this->updateEstado($id, 'Confirmada', 'Cita #%s confirmada.');
    }

    public function cancel(Request $request, $id)
    {
        $data = $request->validate([
            'cancellation_reason' => 'nullable|string|max:500',
        ], [], [
            'cancellation_reason' => 'motivo de cancelacion',
        ]);

        return $this->updateEstado(
            $id,
            'Cancelada',
            'Cita #%s cancelada. El registro se conservó en el sistema.',
            ['cancellation_reason' => trim((string) ($data['cancellation_reason'] ?? '')) ?: null],
            true
        );
    }

    public function attended($id)
    {
        return $this->updateEstado($id, 'Asistida', 'Cita #%s marcada como asistida.');
    }

    public function markReminder($id)
    {
        $appointment = Appointment::findOrFail($id);
        $this->authorizeCalendarAppointmentAccess($appointment);

        $appointment->update(['reminder_sent' => ! $appointment->reminder_sent]);

        $message = $appointment->reminder_sent
            ? "Cita #{$appointment->id} resaltada para seguimiento."
            : "Se quitó el resaltado de la cita #{$appointment->id}.";

        return back()->with('success', $message);
    }

    public function finish(Request $request, $id)
    {
        $appointment = Appointment::with('patient')->findOrFail($id);
        $this->authorizeAppointmentAccess($appointment);

        if ($appointment->estado === 'Cancelada') {
            return redirect()->route('citas')->withErrors([
                'estado' => 'Una cita cancelada no se puede marcar como terminada.',
            ]);
        }

        $data = $request->validate([
            'descuento' => 'nullable|numeric|min:0|max:100',
            'procedimiento_extra' => 'nullable|string|max:255',
            'costo_extra' => 'nullable|numeric|min:0|required_with:procedimiento_extra',
            'completion_description' => 'nullable|string|max:2000',
        ], $this->appointmentValidationMessages(), [
            'descuento' => 'descuento',
            'procedimiento_extra' => 'procedimiento extra',
            'costo_extra' => 'costo adicional',
            'completion_description' => 'descripcion del descuento',
        ]);

        $catalogTreatment = Treatment::whereNull('appointment_id')
            ->whereNull('patient_id')
            ->where('tratamiento', $appointment->tipo)
            ->first();
        $baseCost = (float) ($catalogTreatment?->costo ?? 0);
        $extraCost = $request->has('costo_extra')
            ? (float) ($data['costo_extra'] ?? 0)
            : (float) $appointment->costo_extra;
        $discount = $request->has('descuento')
            ? (float) ($data['descuento'] ?? 0)
            : (float) $appointment->descuento;
        $extraProcedure = $request->has('procedimiento_extra')
            ? (trim((string) ($data['procedimiento_extra'] ?? '')) ?: null)
            : $appointment->procedimiento_extra;
        $completionDescription = $request->has('completion_description')
            ? (trim((string) ($data['completion_description'] ?? '')) ?: null)
            : $appointment->completion_description;

        $appointment->update([
            'descuento' => $discount,
            'procedimiento_extra' => $extraProcedure,
            'costo_extra' => $extraCost,
            'completion_description' => $completionDescription,
        ]);

        if (! app(AppointmentCompletionService::class)->complete($appointment)) {
            return redirect()->route('citas')->withErrors([
                'tratamiento' => 'No se encontro el tratamiento de la cita en el catalogo.',
            ]);
        }

        return redirect()->route('citas')->with('success', "Cita #{$appointment->id} terminada. La ganancia ya se refleja en Reportes.");
    }

    public function reprogram($id)
    {
        $this->authorizeAppointmentAccess(Appointment::findOrFail($id));

        // Reprogramar reutiliza la edicion existente para conservar el mismo formulario del front.
        return redirect()->route('citas.edit', ['id' => $id]);
    }

    public function moveFromCalendar(Request $request, $id)
    {
        $appointment = Appointment::with(['patient', 'dentist'])->findOrFail($id);
        $this->authorizeCalendarAppointmentAccess($appointment);

        if (in_array($appointment->estado, ['Cancelada', 'Terminada'], true)) {
            return response()->json(['message' => 'No puedes mover una cita cancelada o terminada.'], 422);
        }

        $data = $request->validate([
            'fecha' => 'required|date|after_or_equal:today',
            'hora' => 'nullable|date_format:H:i',
            'hora_fin' => 'nullable|date_format:H:i',
        ], $this->appointmentValidationMessages(), $this->appointmentValidationAttributes());

        $start = Carbon::parse($appointment->date->toDateString().' '.$appointment->time);
        $end = $this->appointmentEnd($appointment);
        $time = $data['hora'] ?? $start->format('H:i');
        $endTime = $data['hora_fin'] ?? $end->format('H:i');
        [$time, $endTime] = $this->appointmentTimeRange($data['fecha'], $time, $endTime);
        $newStart = Carbon::createFromFormat('Y-m-d H:i:s', "{$data['fecha']} {$time}");

        if ($newStart->lessThan(now()->startOfMinute())) {
            return response()->json(['message' => 'No puedes cambiar la cita a una fecha u hora que ya paso.'], 422);
        }

        if ($data['fecha'] === $appointment->date->toDateString() && $time === $start->format('H:i:s')) {
            return response()->json(['message' => 'La cita ya esta programada en ese horario.'], 422);
        }

        $participants = $appointment->participatingDentists->isNotEmpty()
            ? $appointment->participatingDentists
            : collect([$appointment->dentist]);
        foreach ($participants as $participant) {
            if ($message = $this->schedulableError($participant, $data['fecha'], $time, $endTime)) {
                return response()->json(['message' => $participant->nombre.': '.$message], 422);
            }

            if ($conflictingAppointment = $this->findConflictingAppointment($participant, $data['fecha'], $time, $endTime, $appointment->id)) {
                return response()->json(['message' => $this->conflictMessage($conflictingAppointment, $participant)], 422);
            }

            if ($conflictingBlock = $this->findConflictingCalendarBlock($participant, $data['fecha'], $time, $endTime)) {
                return response()->json(['message' => $this->calendarBlockConflictMessage($conflictingBlock).' Doctor: '.$participant->nombre.'.'], 422);
            }
        }

        if ($conflictingAppointment = $this->findPatientConflictingAppointment($appointment->patient, $data['fecha'], $time, $endTime, $appointment->id)) {
            return response()->json(['message' => $this->patientConflictMessage($conflictingAppointment)], 422);
        }

        $appointment->update([
            'date' => $data['fecha'],
            'time' => $time,
            'end_time' => $endTime,
        ]);

        return response()->json([
            'message' => 'Cita reprogramada correctamente.',
            'appointment' => $this->serializeAppointment($appointment->fresh(['patient', 'dentist'])),
        ]);
    }

    public function destroy($id)
    {
        $appointment = Appointment::find($id);

        if ($appointment) {
            $this->authorizeAppointmentAccess($appointment);
            $appointment->update(['estado' => 'Cancelada']);

            return redirect()->route('citas')->with('success', "Cita #{$id} cancelada. El registro se conservó en el sistema.");
        }

        return redirect()->route('citas')->with('success', "Cita #{$id} no encontrada.");
    }

    public function show($id)
    {
        $appointment = Appointment::findOrFail($id);
        $this->authorizeCalendarAppointmentAccess($appointment);
        $catalogTreatment = Treatment::whereNull('appointment_id')
            ->whereNull('patient_id')
            ->where('tratamiento', $appointment->tipo)
            ->first();
        $completedTreatment = Treatment::where('appointment_id', $appointment->id)->first();
        $baseCost = (float) ($catalogTreatment?->costo ?? 0);
        $extraCost = (float) $appointment->costo_extra;
        $discount = (float) $appointment->descuento;
        $finalCost = $completedTreatment
            ? (float) $completedTreatment->costo
            : max(0, ($baseCost + $extraCost) * (1 - ($discount / 100)));

        $backUrl = $this->resolveBackUrl(request(), $appointment);

        return view('citas.detalle', [
            'cita' => $this->serializeAppointment($appointment),
            'baseCost' => $baseCost,
            'extraCost' => $extraCost,
            'discount' => $discount,
            'finalCost' => $finalCost,
            'backUrl' => $backUrl,
            'catalogTreatments' => $this->catalogTreatments(),
        ]);
    }

    private function updateEstado($id, string $estado, string $message, array $extra = [], bool $allowAnyAssistant = false)
    {
        abort_unless(in_array($estado, self::VALID_STATUSES, true), 422);

        $appointment = Appointment::find($id);

        if (! $appointment) {
            return redirect()->route('citas')->with('success', sprintf("Cita #%s no encontrada.", $id));
        }

        if ($allowAnyAssistant) {
            $this->authorizeCalendarAppointmentAccess($appointment);
        } else {
            $this->authorizeAppointmentAccess($appointment);
        }
        $appointment->update(array_merge(['estado' => $estado], $extra));

        return redirect()->route('citas')->with('success', sprintf($message, $id));
    }

    private function resolveBackUrl(Request $request, Appointment $appointment): string
    {
        $fallback = route('citas');
        $candidate = $request->input('return_to')
            ?: $request->query('return_to')
            ?: $request->headers->get('referer');

        if (! $candidate) {
            return $fallback;
        }

        $candidateHost = parse_url($candidate, PHP_URL_HOST);
        $candidatePath = '/'.ltrim((string) parse_url($candidate, PHP_URL_PATH), '/');
        $currentPath = '/'.ltrim($request->getPathInfo(), '/');
        $isInternal = $candidateHost === null || $candidateHost === $request->getHost();
        $isCurrentPage = rtrim($candidatePath, '/') === rtrim($currentPath, '/');

        return $isInternal && ! $isCurrentPage ? $candidate : $fallback;
    }

    private function catalogTreatments()
    {
        return Treatment::whereNull('appointment_id')
            ->whereNull('patient_id')
            ->orderBy('tratamiento')
            ->get(['tratamiento', 'tipo', 'costo', 'color'])
            ->unique('tratamiento')
            ->values();
    }

    private function serializeAppointment(Appointment $appointment): array
    {
        $start = Carbon::parse($appointment->date->toDateString().' '.$appointment->time);
        $end = $this->appointmentEnd($appointment);

        return [
            'id' => $appointment->id,
            'paciente' => $appointment->patient?->nombre ?? $appointment->paciente,
            'dentista' => $appointment->dentist?->nombre ?? $appointment->dentista,
            'fecha' => $appointment->date?->toDateString() ?? $appointment->date,
            'hora' => $start->format('h:i A'),
            'hora_inicio' => $start->format('H:i'),
            'hora_fin' => $end->format('H:i'),
            'color' => $this->treatmentColor($appointment->tipo, $appointment->color),
            'descuento' => (float) $appointment->descuento,
            'procedimiento_extra' => $appointment->procedimiento_extra,
            'costo_extra' => (float) $appointment->costo_extra,
            'completion_description' => $appointment->completion_description,
            'base_cost' => (float) (Treatment::query()
                ->whereNull('appointment_id')
                ->whereNull('patient_id')
                ->where('tratamiento', $appointment->tipo)
                ->value('costo') ?? 0),
            'cancellation_reason' => $appointment->cancellation_reason,
            'horario' => $start->format('h:i A').' - '.$end->format('h:i A'),
            'tipo' => $appointment->tipo,
            'estado' => $appointment->estado,
            'collaborative' => $appointment->collaborators()->exists(),
            'collaborators' => $appointment->collaborators()->pluck('dentists.nombre')->values()->all(),
        ];
    }

    private function authorizeAppointmentAccess(Appointment $appointment): void
    {
        if (session('access_role') === 'doctor') {
            $dentistId = $this->currentDoctorDentist()?->id;
            abort_unless(
                $appointment->dentist_id === $dentistId
                || $appointment->participatingDentists()->whereKey($dentistId)->exists(),
                403
            );
            return;
        }

        if (session('access_role') === 'asistente') {
            $assistantUserId = $this->currentAssistantUserId();
            abort_unless(
                $assistantUserId !== null
                && (int) $appointment->created_by_user_id === $assistantUserId,
                403
            );
            return;
        }
    }

    private function authorizeCalendarAppointmentAccess(Appointment $appointment): void
    {
        if (session('access_role') === 'doctor') {
            $dentistId = $this->currentDoctorDentist()?->id;
            abort_unless(
                $appointment->dentist_id === $dentistId
                || $appointment->participatingDentists()->whereKey($dentistId)->exists(),
                403
            );
            return;
        }

        if ($this->currentAssistantUserId()) {
            return;
        }

        $this->authorizeAppointmentAccess($appointment);
    }

    private function scopeAppointmentsForCurrentUser($query)
    {
        return $query;
    }

    private function scopeForDentistParticipation($query, int $dentistId)
    {
        return $query->where(function ($appointments) use ($dentistId) {
            $appointments->where('dentist_id', $dentistId)
                ->orWhereHas('participatingDentists', fn ($participants) => $participants->whereKey($dentistId));
        });
    }

    private function currentAssistantUserId(): ?int
    {
        $userId = session('access_user_id');

        if (session('access_role') !== 'asistente' || ! $userId) {
            return null;
        }

        return User::whereKey($userId)->where('role', 'asistente')->exists()
            ? (int) $userId
            : null;
    }

    private function resolvePatient(string $name): Patient
    {
        return Patient::where('nombre', $name)->firstOrFail();
    }

    private function resolveDentist(string $name): Dentist
    {
        return Dentist::where('nombre', $name)->firstOrFail();
    }

    private function resolveAppointmentTreatmentName(string $selectedTreatment, ?string $customTreatment, mixed $customCost = null): string
    {
        $selectedTreatment = trim($selectedTreatment);
        $customTreatment = trim((string) $customTreatment);

        if ($selectedTreatment === self::CUSTOM_TREATMENT_OPTION) {
            if ($customTreatment === '') {
                back()
                    ->withErrors(['tratamiento_emergencia' => 'Escribe el tratamiento de emergencia.'])
                    ->withInput()
                    ->throwResponse();
            }

            $treatment = Treatment::firstOrNew([
                'appointment_id' => null,
                'patient_id' => null,
                'tratamiento' => $customTreatment,
            ]);

            $treatment->fill([
                'paciente' => '',
                'tipo' => 'Emergencia',
                'fecha' => $treatment->exists ? $treatment->fecha : now()->toDateString(),
                'estado' => 'Activo',
                'color' => $treatment->color ?: '#ef4444',
                'costo' => $customCost !== null && $customCost !== '' ? (float) $customCost : (float) ($treatment->costo ?? 0),
                'descripcion' => $treatment->descripcion ?: 'Agregado desde agenda de citas como tratamiento de emergencia.',
            ])->save();

            return $treatment->tratamiento;
        }

        if (! Treatment::whereNull('appointment_id')->whereNull('patient_id')->where('tratamiento', $selectedTreatment)->exists()) {
            back()
                ->withErrors(['tipo' => 'El tratamiento seleccionado no existe en el catalogo.'])
                ->withInput()
                ->throwResponse();
        }

        return $selectedTreatment;
    }

    private function currentDoctorDentist(): ?Dentist
    {
        if (session('access_role') !== 'doctor') {
            return null;
        }

        $user = User::find(session('access_user_id'))
            ?? User::where('email', session('access_email'))->first();

        if (! $user && session('access_email')) {
            $user = User::create([
                'name' => session('access_name') ?: Str::before(session('access_email'), '@'),
                'email' => session('access_email'),
                'role' => 'doctor',
                'password' => Hash::make(Str::random(32)),
            ]);

            session([
                'access_user_id' => $user->id,
                'access_name' => $user->name,
            ]);
        }

        abort_unless($user, 403, 'La sesion activa no esta vinculada a un usuario dentista.');

        $dentist = Dentist::where('user_id', $user->id)->first()
            ?? Dentist::whereNull('user_id')->where('nombre', $user->name)->first();

        if ($dentist) {
            if (! $dentist->user_id) {
                $dentist->update(['user_id' => $user->id]);
            }

            return $dentist;
        }

        return Dentist::create([
            'user_id' => $user->id,
            'nombre' => $user->name,
            'especialidad' => 'Odontologia general',
            'telefono' => 'No registrado',
        ]);
    }

    private function findConflictingAppointment(Dentist $dentist, string $date, string $time, string $endTime, ?int $ignoreAppointmentId = null): ?Appointment
    {
        $appointments = Appointment::query()
            ->where(function ($query) use ($dentist) {
                $query->where('dentist_id', $dentist->id)
                    ->orWhereHas('participatingDentists', fn ($participants) => $participants->whereKey($dentist->id));
            })
            ->whereNotIn('estado', ['Cancelada', 'Terminada'])
            ->when($ignoreAppointmentId, fn ($query) => $query->whereKeyNot($ignoreAppointmentId))
            ->orderBy('time')
            ->get();

        return $this->firstAppointmentOverlapping($appointments, $date, $time, $endTime);
    }

    private function findPatientConflictingAppointment(Patient $patient, string $date, string $time, string $endTime, ?int $ignoreAppointmentId = null): ?Appointment
    {
        $appointments = Appointment::query()
            ->where('patient_id', $patient->id)
            ->whereNotIn('estado', ['Cancelada', 'Terminada'])
            ->when($ignoreAppointmentId, fn ($query) => $query->whereKeyNot($ignoreAppointmentId))
            ->orderBy('time')
            ->get();

        return $this->firstAppointmentOverlapping($appointments, $date, $time, $endTime);
    }

    private function findConflictingCalendarBlock(Dentist $dentist, string $date, string $time, string $endTime): ?CalendarBlock
    {
        $requestedStart = Carbon::createFromFormat('Y-m-d H:i:s', "{$date} {$time}");
        $requestedEnd = Carbon::createFromFormat('Y-m-d H:i:s', "{$date} {$endTime}");

        return CalendarBlock::where('dentist_id', $dentist->id)
            ->whereDate('date', $date)
            ->orderBy('start_time')
            ->get()
            ->first(function (CalendarBlock $block) use ($requestedStart, $requestedEnd) {
                $blockStart = Carbon::parse($block->date->toDateString().' '.$block->start_time);
                $blockEnd = Carbon::parse($block->date->toDateString().' '.$block->end_time);

                return $requestedStart->lt($blockEnd) && $requestedEnd->gt($blockStart);
            });
    }

    private function firstAppointmentOverlapping($appointments, string $date, string $time, string $endTime): ?Appointment
    {
        $requestedStart = Carbon::createFromFormat('Y-m-d H:i:s', "{$date} {$time}");
        $requestedEnd = Carbon::createFromFormat('Y-m-d H:i:s', "{$date} {$endTime}");

        return $appointments->first(function (Appointment $appointment) use ($date, $requestedStart, $requestedEnd) {
            if ($appointment->date->toDateString() !== $date) {
                return false;
            }

            $existingStart = Carbon::parse($appointment->date->toDateString().' '.$appointment->time);
            $existingEnd = $this->appointmentEnd($appointment);

            return $requestedStart->lt($existingEnd) && $requestedEnd->gt($existingStart);
        });
    }

    private function conflictMessage(Appointment $appointment, ?Dentist $dentist = null): string
    {
        $start = Carbon::parse($appointment->date->toDateString().' '.$appointment->time);
        $end = $this->appointmentEnd($appointment);

        return sprintf(
            '%s ya tiene una cita de %s a %s. Elige una hora de inicio y termino fuera de ese bloque.',
            $dentist?->nombre ?? 'El dentista',
            $start->format('H:i'),
            $end->format('H:i')
        );
    }

    private function patientConflictMessage(Appointment $appointment): string
    {
        $start = Carbon::parse($appointment->date->toDateString().' '.$appointment->time);
        $end = $this->appointmentEnd($appointment);

        return sprintf(
            'El paciente ya tiene una cita de %s a %s con %s. Elige otro horario para ese paciente.',
            $start->format('H:i'),
            $end->format('H:i'),
            $appointment->dentist?->nombre ?? 'otro dentista'
        );
    }

    private function calendarBlockConflictMessage(CalendarBlock $block): string
    {
        $start = date('H:i', strtotime($block->start_time));
        $end = date('H:i', strtotime($block->end_time));

        if ($start === '00:00' && $end === '23:59') {
            return "Ese día está marcado como {$block->type}; no se pueden agendar citas.";
        }

        return "El horario está bloqueado por {$block->type} de {$start} a {$end}.";
    }

    private function appointmentTimeRange(string $date, string $startTime, ?string $endTime): array
    {
        $start = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$startTime}");
        $end = $endTime
            ? Carbon::createFromFormat('Y-m-d H:i', "{$date} {$endTime}")
            : $start->copy()->addMinutes($this->appointmentDuration());

        if ($end->lessThanOrEqualTo($start)) {
            back()
                ->withErrors(['hora_fin' => 'La hora de termino debe ser posterior a la hora de inicio.'])
                ->withInput()
                ->throwResponse();
        }

        return [$start->format('H:i:s'), $end->format('H:i:s')];
    }

    private function ensureAppointmentIsSchedulable(Dentist $dentist, string $date, string $time, string $endTime): void
    {
        $requestedStart = Carbon::createFromFormat('Y-m-d H:i:s', "{$date} {$time}");

        if ($requestedStart->lessThan(now()->startOfMinute())) {
            back()
                ->withErrors(['hora' => 'No puedes agendar una cita en una hora que ya paso.'])
                ->withInput()
                ->throwResponse();
        }

        if ($message = $this->schedulableError($dentist, $date, $time, $endTime)) {
            back()
                ->withErrors(['hora' => $message])
                ->withInput()
                ->throwResponse();
        }
    }

    private function schedulableError(Dentist $dentist, string $date, string $time, string $endTime): ?string
    {
        $requestedStart = Carbon::createFromFormat('Y-m-d H:i:s', "{$date} {$time}");
        $requestedEnd = Carbon::createFromFormat('Y-m-d H:i:s', "{$date} {$endTime}");
        $weekday = $requestedStart->isoWeekday();
        $schedule = DentistSchedule::firstOrCreate(
            ['dentist_id' => $dentist->id, 'weekday' => $weekday],
            [
                'enabled' => $weekday <= 5,
                'start_time' => '09:00',
                'end_time' => $weekday === 5 ? '15:00' : '17:00',
            ]
        );

        if (! $schedule->enabled) {
            return sprintf(
                'El dentista no trabaja el %s; es un día inhábil.',
                $requestedStart->locale('es')->isoFormat('dddd')
            );
        }

        $scheduleStart = Carbon::parse("{$date} {$schedule->start_time}");
        $scheduleEnd = Carbon::parse("{$date} {$schedule->end_time}");

        if ($requestedStart->lt($scheduleStart) || $requestedEnd->gt($scheduleEnd)) {
            return sprintf(
                'El dentista trabaja de %s a %s; la hora solicitada no está disponible.',
                $scheduleStart->format('h:i A'),
                $scheduleEnd->format('h:i A')
            );
        }

        return null;
    }

    private function appointmentEnd(Appointment $appointment): Carbon
    {
        $start = Carbon::parse($appointment->date->toDateString().' '.$appointment->time);

        if ($appointment->end_time) {
            return Carbon::parse($appointment->date->toDateString().' '.$appointment->end_time);
        }

        return $start->copy()->addMinutes($this->appointmentDuration());
    }

    private function treatmentColor(string $treatmentName, ?string $fallback = null): string
    {
        return Treatment::whereNull('appointment_id')
            ->whereNull('patient_id')
            ->where('tratamiento', $treatmentName)
            ->value('color')
            ?: ($fallback ?: '#9700cf');
    }

    private function appointmentValidationMessages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'date' => 'El campo :attribute debe ser una fecha valida.',
            'date_format' => 'El campo :attribute debe tener un formato de hora valido.',
            'after_or_equal' => 'La fecha no puede ser anterior a hoy.',
            'exists' => 'El :attribute seleccionado no existe.',
            'in' => 'El :attribute seleccionado no es valido.',
            'regex' => 'El :attribute seleccionado no es valido.',
            'numeric' => 'El campo :attribute debe ser una cantidad valida.',
            'min' => 'El campo :attribute no puede ser negativo.',
            'max' => 'El campo :attribute no puede superar :max caracteres.',
            'required_with' => 'Indica el :attribute del procedimiento extra.',
        ];
    }

    private function appointmentValidationAttributes(): array
    {
        return [
            'paciente' => 'paciente',
            'nuevo_paciente_nombre' => 'nombre del nuevo paciente',
            'nuevo_paciente_telefono' => 'numero de emergencia',
            'dentista' => 'dentista',
            'fecha' => 'fecha',
            'hora' => 'hora de inicio',
            'hora_fin' => 'hora de término',
            'tipo' => 'tratamiento',
            'tratamiento_emergencia' => 'tratamiento de emergencia',
            'costo_emergencia' => 'precio de emergencia',
            'estado' => 'estado',
            'color' => 'color',
            'descuento' => 'descuento',
            'procedimiento_extra' => 'procedimiento extra',
            'costo_extra' => 'costo adicional',
        ];
    }

    private function displayTreatmentName(string $baseTreatment, ?string $extraTreatment): string
    {
        $extraTreatment = trim((string) $extraTreatment);

        return $extraTreatment === ''
            ? $baseTreatment
            : $baseTreatment.' + '.$extraTreatment;
    }

    private function normalizeExtraTreatments(mixed $names, mixed $costs): array
    {
        $names = is_array($names) ? array_values($names) : [$names];
        $costs = is_array($costs) ? array_values($costs) : [$costs];
        $cleanNames = [];
        $totalCost = 0.0;

        foreach ($names as $index => $name) {
            $name = trim((string) $name);
            $cost = (float) ($costs[$index] ?? 0);

            if ($name !== '') {
                $cleanNames[] = $name;
                $catalogCost = (float) (Treatment::query()
                    ->whereNull('appointment_id')
                    ->whereNull('patient_id')
                    ->where('tratamiento', $name)
                    ->value('costo') ?? 0);
                $totalCost += $catalogCost;
            }

            if ($cost > 0) {
                $totalCost += $cost;
            }
        }

        return [
            $cleanNames ? implode(' + ', $cleanNames) : null,
            $totalCost,
        ];
    }

    private function appointmentDuration(): int
    {
        return ClinicSetting::current()->appointment_duration;
    }

    private function syncAppointmentParticipants(Appointment $appointment, Dentist $primary, ?Dentist $collaborator): void
    {
        $participants = [
            $primary->id => ['participation_role' => 'primary'],
        ];

        if ($collaborator) {
            $participants[$collaborator->id] = ['participation_role' => 'collaborator'];
        }

        $appointment->participatingDentists()->sync($participants);
    }
}
