<?php

namespace App\Http\Controllers;

use App\Models\Dentist;
use App\Models\MedicalHistory;
use App\Models\Patient;
use Illuminate\Http\Request;

class ClinicalRecordController extends Controller
{
    public function store(Request $request, Patient $patient)
    {
        $this->authorizeDoctor();
        $data = $request->validate([
            'dentist_id' => session('access_role') === 'admin' ? 'required|exists:dentists,id' : 'nullable',
            'recorded_at' => 'required|date',
            'reason' => 'required|string|max:255',
            'diagnosis' => 'nullable|string|max:3000',
            'procedure' => 'nullable|string|max:3000',
            'notes' => 'nullable|string|max:5000',
            'prescription' => 'nullable|string|max:3000',
            'next_visit' => 'nullable|date',
        ]);

        $dentistId = session('access_role') === 'admin'
            ? (int) $data['dentist_id']
            : $this->dentist()?->id;
        unset($data['dentist_id']);

        $patient->clinicalRecords()->create($data + ['dentist_id' => $dentistId]);

        return redirect()->route('pacientes.expediente', ['paciente' => $patient, 'tab' => 'consultas'])
            ->with('success', 'Consulta clínica registrada.');
    }

    public function updateMedicalHistory(Request $request, Patient $patient)
    {
        $this->authorizeDoctor();
        $data = $request->validate([
            'blood_type' => 'nullable|string|max:10',
            'allergies' => 'nullable|string|max:2000',
            'conditions' => 'nullable|string|max:2000',
            'medications' => 'nullable|string|max:2000',
            'surgeries' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:3000',
        ]);

        MedicalHistory::updateOrCreate(['patient_id' => $patient->id], $data);

        return redirect()->route('pacientes.expediente', ['paciente' => $patient, 'tab' => 'antecedentes'])
            ->with('success', 'Antecedentes médicos actualizados.');
    }

    private function dentist(): ?Dentist
    {
        // La nota clínica debe quedar ligada al doctor autenticado, no a un nombre que puede repetirse o cambiar.
        return Dentist::where('user_id', session('access_user_id'))->first();
    }

    private function authorizeDoctor(): void
    {
        abort_unless(in_array(session('access_role'), ['doctor', 'admin'], true), 403);
    }
}
