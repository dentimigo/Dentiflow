<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Dentist;
use App\Models\Patient;
use App\Models\Treatment;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminReportController extends Controller
{
    public function index(Request $request)
    {
        $search = trim($request->query('search', ''));

        $reports = Treatment::with(['patient', 'appointment.dentist', 'appointment.patient'])
            ->where('estado', 'Realizado')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('tratamiento', 'like', "%{$search}%")
                        ->orWhere('paciente', 'like', "%{$search}%")
                        ->orWhereHas('patient', fn ($patientQuery) => $patientQuery->where('nombre', 'like', "%{$search}%"))
                        ->orWhereHas('appointment.dentist', fn ($dentistQuery) => $dentistQuery->where('nombre', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('fecha')
            ->paginate(10)
            ->withQueryString();

        $summary = [
            'reports' => Treatment::where('estado', 'Realizado')->count(),
            'earnings' => Treatment::where('estado', 'Realizado')->sum('costo'),
            'finishedAppointments' => Appointment::where('estado', 'Terminada')->count(),
        ];

        return view('admin.reports.index', compact('reports', 'summary', 'search'));
    }

    public function editAppointment(Appointment $appointment)
    {
        abort_unless($appointment->estado === 'Terminada', 404);

        return view('admin.reports.edit-appointment', [
            'appointment' => $appointment->load(['patient', 'dentist', 'treatments']),
            'patients' => Patient::orderBy('nombre')->get(['id', 'nombre']),
            'dentists' => Dentist::orderBy('nombre')->get(['id', 'nombre']),
            'catalogTreatments' => Treatment::query()
                ->whereNull('appointment_id')
                ->whereNull('patient_id')
                ->orderBy('tratamiento')
                ->get(['tratamiento', 'tipo', 'costo']),
        ]);
    }

    public function updateAppointment(Request $request, Appointment $appointment)
    {
        abort_unless($appointment->estado === 'Terminada', 404);

        $data = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'dentist_id' => 'required|exists:dentists,id',
            'date' => 'required|date',
            'time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'tipo' => 'required|string|exists:treatments,tratamiento',
        ], $this->validationMessages(), $this->validationAttributes());

        $start = Carbon::createFromFormat('Y-m-d H:i', "{$data['date']} {$data['time']}");
        $end = Carbon::createFromFormat('Y-m-d H:i', "{$data['date']} {$data['end_time']}");

        if ($end->lessThanOrEqualTo($start)) {
            return back()
                ->withErrors(['end_time' => 'La hora de terminado debe ser posterior a la hora de inicio.'])
                ->withInput();
        }

        $appointment->update([
            'patient_id' => $data['patient_id'],
            'dentist_id' => $data['dentist_id'],
            'date' => $data['date'],
            'time' => $start->format('H:i:s'),
            'end_time' => $end->format('H:i:s'),
            'tipo' => $data['tipo'],
        ]);

        $this->syncReportWithAppointment($appointment->fresh(['patient']));

        return redirect()
            ->route('admin.reports')
            ->with('success', 'Cita terminada actualizada correctamente.');
    }

    public function destroy(Treatment $treatment)
    {
        abort_unless($treatment->estado === 'Realizado', 404);

        $treatment->delete();

        return redirect()
            ->route('admin.reports')
            ->with('success', 'Reporte eliminado correctamente. La cita terminada se conserva en el historial.');
    }

    private function syncReportWithAppointment(Appointment $appointment): void
    {
        $catalogTreatment = Treatment::query()
            ->whereNull('appointment_id')
            ->whereNull('patient_id')
            ->where('tratamiento', $appointment->tipo)
            ->first();

        Treatment::where('appointment_id', $appointment->id)
            ->where('estado', 'Realizado')
            ->update([
                'patient_id' => $appointment->patient_id,
                'paciente' => $appointment->patient?->nombre ?? '',
                'tratamiento' => $appointment->tipo,
                'tipo' => $catalogTreatment?->tipo ?? $appointment->tipo,
                'fecha' => $appointment->date?->toDateString() ?? $appointment->date,
                'costo' => $catalogTreatment?->costo ?? 0,
                'descripcion' => $catalogTreatment?->descripcion,
            ]);
    }

    private function validationMessages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'exists' => 'El :attribute seleccionado no existe.',
            'date' => 'El campo :attribute debe ser una fecha valida.',
            'date_format' => 'El campo :attribute debe tener un formato de hora valido.',
        ];
    }

    private function validationAttributes(): array
    {
        return [
            'patient_id' => 'paciente',
            'dentist_id' => 'dentista',
            'date' => 'fecha',
            'time' => 'hora de inicio',
            'end_time' => 'hora de terminado',
            'tipo' => 'tratamiento',
        ];
    }
}
