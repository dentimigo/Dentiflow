<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Dentist;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\User;
use App\Services\AppointmentCompletionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        $search = trim($request->query('search', ''));
        $order = $request->query('order', 'visita_desc');

        $query = Patient::query();

        if ($search !== '') {
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('nombre', 'like', "%{$search}%")
                    ->orWhere('telefono', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $query = match ($order) {
            'visita_asc' => $query->orderBy('ultima_visita', 'asc'),
            'nombre_asc' => $query->orderBy('nombre', 'asc'),
            'nombre_desc' => $query->orderBy('nombre', 'desc'),
            default => $query->orderBy('ultima_visita', 'desc'),
        };

        $pacientes = $query->paginate(12)->withQueryString();

        return view('pacientes.index', compact('pacientes'));
    }

    public function create()
    {
        return view('pacientes.crear');
    }

    public function records(Request $request)
    {
        $search = trim($request->query('search', ''));
        $order = $request->query('order', 'reciente');
        $isDoctor = $request->session()->get('access_role') === 'doctor';
        $doctorDentist = $isDoctor ? $this->sessionDentist($request) : null;

        $baseQuery = Patient::query()
            ->withCount(['appointments', 'clinicalRecords', 'treatments'])
            ->withMax('appointments', 'date')
            ->withMax('clinicalRecords', 'recorded_at');

        if ($isDoctor) {
            $baseQuery->where(function ($query) use ($doctorDentist) {
                $query->whereHas('appointments', fn ($appointmentQuery) => $appointmentQuery->where('dentist_id', $doctorDentist?->id ?? 0))
                    ->orWhereHas('clinicalRecords', fn ($recordQuery) => $recordQuery->where('dentist_id', $doctorDentist?->id ?? 0))
                    ->orWhereHas('treatments.appointment', fn ($appointmentQuery) => $appointmentQuery->where('dentist_id', $doctorDentist?->id ?? 0));
            });
        }

        if ($search !== '') {
            $baseQuery->where(function ($query) use ($search) {
                $query->where('nombre', 'like', "%{$search}%")
                    ->orWhere('telefono', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $summaryQuery = clone $baseQuery;

        $query = match ($order) {
            'nombre_asc' => $baseQuery->orderBy('nombre'),
            'nombre_desc' => $baseQuery->orderByDesc('nombre'),
            'consultas_desc' => $baseQuery->orderByDesc('clinical_records_count')->orderBy('nombre'),
            default => $baseQuery->orderByDesc('clinical_records_max_recorded_at')->orderByDesc('appointments_max_date')->orderBy('nombre'),
        };

        $expedientes = $query->paginate(12)->withQueryString();

        $resumen = [
            'total' => (clone $summaryQuery)->count(),
            'conConsultas' => (clone $summaryQuery)->has('clinicalRecords')->count(),
            'conCitas' => (clone $summaryQuery)->has('appointments')->count(),
            'conTratamientos' => (clone $summaryQuery)->has('treatments')->count(),
        ];

        return view('expedientes.index', compact('expedientes', 'resumen', 'search', 'order'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255|regex:/^[\pL\s]+$/u',
            'telefono' => 'required|digits:10',
            'email' => 'nullable|email|max:255|unique:patients,email',
            'direccion' => 'required|string|max:500',
            'ultima_visita' => 'nullable|date',
            'estado' => 'nullable|string|max:50',
            'seguro' => 'nullable|string|max:150',
            'poliza' => 'nullable|string|max:100',
            'contacto_emergencia.nombre' => 'nullable|string|max:255|regex:/^[\pL\s]+$/u',
            'contacto_emergencia.telefono' => 'nullable|digits:10',
            'contacto_emergencia.relacion' => 'nullable|string|max:100',
        ], $this->validationMessages());

        $data = $this->normalizePatientData($data);

        $patient = Patient::create($data);

        if ($request->input('origen') === 'expedientes') {
            return redirect()
                ->route('pacientes.expediente', ['paciente' => $patient, 'tab' => 'resumen'])
                ->with('success', 'Expediente creado correctamente.');
        }

        return redirect()->route('pacientes')->with('success', 'Paciente agregado correctamente.');
    }

    public function edit($paciente)
    {
        $patient = $this->resolvePatient($paciente);

        return view('pacientes.editar', ['paciente' => [
            'id' => $patient->id,
            'nombre' => $patient->nombre,
            'telefono' => $patient->telefono,
            'email' => $patient->email,
            'direccion' => $patient->direccion,
            'seguro' => $patient->seguro,
            'poliza' => $patient->poliza,
            'contacto_emergencia' => $patient->contacto_emergencia ?? [],
            'estado' => $patient->estado,
            'ultima_visita' => $patient->ultima_visita?->format('d/m/Y'),
            'ultima_visita_input' => $patient->ultima_visita?->format('Y-m-d'),
            'key' => $patient->id,
        ]]);
    }

    public function update(Request $request, $paciente)
    {
        $patient = $this->resolvePatient($paciente);

        $data = $request->validate([
            'nombre' => 'required|string|max:255|regex:/^[\pL\s]+$/u',
            'telefono' => 'required|digits:10',
            'email' => ['nullable', 'email', 'max:255', Rule::unique('patients', 'email')->ignore($patient->id)],
            'direccion' => 'nullable|string|max:500',
            'ultima_visita' => 'nullable|date',
            'estado' => 'nullable|string|max:50',
            'seguro' => 'nullable|string|max:150',
            'poliza' => 'nullable|string|max:100',
            'contacto_emergencia.nombre' => 'nullable|string|max:255|regex:/^[\pL\s]+$/u',
            'contacto_emergencia.telefono' => 'nullable|digits:10',
            'contacto_emergencia.relacion' => 'nullable|string|max:100',
        ], $this->validationMessages());

        $data = $this->normalizePatientData($data);

        $patient->update($data);

        return redirect()->route('pacientes')->with('success', 'Paciente actualizado correctamente.');
    }

    public function destroy(Request $request, $paciente)
    {
        $patient = $this->resolvePatient($paciente);
        $patient->delete();

        if ($request->wantsJson() || $request->ajax() || $request->isMethod('delete')) {
            return response()->json(['success' => true, 'message' => "Paciente {$patient->nombre} eliminado correctamente."]);
        }

        return redirect()->route('pacientes')->with('success', "Paciente {$patient->nombre} eliminado correctamente.");
    }

    public function cancel($paciente)
    {
        $patient = $this->resolvePatient($paciente);
        $patient->update(['estado' => 'Cancelada']);

        return redirect()->route('pacientes')->with('success', "El estado de {$patient->nombre} se actualizó a Cancelada.");
    }

    public function show(Request $request, $paciente)
    {
        $patient = $this->resolvePatient($paciente);
        $isDoctor = $request->session()->get('access_role') === 'doctor';
        $doctorDentist = $isDoctor
            ? $this->sessionDentist($request)
            : null;
        $patient->load([
            'medicalHistory',
            'clinicalRecords' => fn ($query) => $query->with('dentist')->orderByDesc('recorded_at'),
            'appointments' => fn ($query) => $query
                ->with(['dentist', 'treatments'])
                ->when($isDoctor, fn ($appointmentQuery) => $appointmentQuery->where('dentist_id', $doctorDentist?->id ?? 0))
                ->orderByDesc('date')
                ->orderByDesc('time'),
        ]);

        $nextAppointment = $patient->appointments()
            ->with('dentist')
            ->when($isDoctor, fn ($query) => $query->where('dentist_id', $doctorDentist?->id ?? 0))
            ->whereDate('date', '>=', now()->toDateString())
            ->whereNotIn('estado', ['Cancelada', 'Asistida', 'Terminada'])
            ->orderBy('date')
            ->orderBy('time')
            ->first();

        $dentists = $request->session()->get('access_role') === 'admin'
            ? Dentist::orderBy('nombre')->get(['id', 'nombre'])
            : collect();

        return view('pacientes.expediente', compact('patient', 'nextAppointment', 'dentists'));
    }

    public function history(Request $request)
    {
        $isDoctor = $request->session()->get('access_role') === 'doctor';
        $doctorDentist = $isDoctor ? $this->sessionDentist($request) : null;

        app(AppointmentCompletionService::class)->completeOverdue($doctorDentist?->id);

        $tratamientos = Treatment::with(['patient', 'appointment.dentist'])
            ->where('estado', 'Realizado')
            ->when($isDoctor, function ($query) use ($doctorDentist) {
                $query->whereHas('appointment', fn ($appointmentQuery) => $appointmentQuery->where('dentist_id', $doctorDentist?->id ?? 0));
            })
            ->orderByDesc('fecha')
            ->get()
            ->map(function ($treatment) {
                $appointment = $treatment->appointment;
                $startTime = $appointment?->time;
                $endTime = $appointment?->end_time;

                return [
                    'treatment_id' => $treatment->id,
                    'appointment_id' => $appointment?->id,
                    'patient_id' => $treatment->patient_id,
                    'paciente' => $treatment->patient?->nombre ?? $treatment->paciente,
                    'tratamiento' => $treatment->tratamiento,
                    'fecha' => $treatment->fecha?->format('d/m/Y'),
                    'hora' => $startTime ? date('h:i A', strtotime($startTime)) : 'Sin cita',
                    'horario' => $startTime
                        ? date('h:i A', strtotime($startTime)).($endTime ? ' - '.date('h:i A', strtotime($endTime)) : '')
                        : 'Sin cita',
                    'dentista' => $appointment?->dentist?->nombre ?? 'Sin dentista',
                    'estado' => $treatment->estado,
                    'costo' => $treatment->costo,
                ];
            });

        $totalPacientes = Patient::count();
        $totalDinero = $tratamientos->sum('costo');
        $tratamientosCount = $tratamientos->where('costo', '>', 0)->groupBy('tratamiento')->map->count();

        return view('pacientes.historial', [
            'historial' => $tratamientos,
            'totalPacientes' => $totalPacientes,
            'totalDinero' => $totalDinero,
            'tratamientos' => $tratamientosCount,
        ]);
    }

    public function destroyHistoryRecord(Request $request, Treatment $treatment)
    {
        abort_unless($treatment->estado === 'Realizado', 404);

        if ($request->session()->get('access_role') === 'doctor') {
            $dentist = $this->sessionDentist($request);
            abort_unless($treatment->appointment?->dentist_id === $dentist?->id, 403);
        }

        DB::transaction(function () use ($treatment) {
            $appointment = $treatment->appointment;
            $treatment->delete();
            $appointment?->delete();
        });

        return redirect()
            ->route('pacientes.historial')
            ->with('success', 'El registro del historial fue eliminado correctamente.');
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

    private function resolvePatient($value): Patient
    {
        if (is_numeric($value)) {
            return Patient::findOrFail($value);
        }

        return Patient::where('nombre', $value)->firstOrFail();
    }

    private function normalizePatientData(array $data): array
    {
        $data['email'] = trim((string) ($data['email'] ?? '')) ?: null;
        $data['ultima_visita'] = $data['ultima_visita'] ?? null;
        $data['estado'] = $data['estado'] ?? 'Activo';

        return $data;
    }

    private function validationMessages(): array
    {
        return [
            'email.unique' => 'Ese correo ya esta registrado en otro paciente.',
            'email.email' => 'Escribe un correo valido.',
            'nombre.regex' => 'El nombre completo solo debe contener letras y espacios.',
            'telefono.digits' => 'El teléfono debe contener exactamente 10 números.',
            'direccion.required' => 'La dirección es obligatoria.',
            'contacto_emergencia.nombre.regex' => 'El nombre del contacto de emergencia solo debe contener letras y espacios.',
            'contacto_emergencia.telefono.digits' => 'El teléfono del contacto de emergencia debe contener exactamente 10 números.',
            'ultima_visita.date' => 'La ultima visita debe ser una fecha valida.',
        ];
    }
}
