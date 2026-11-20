<?php

namespace App\Http\Controllers;

use App\Models\Treatment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TreatmentController extends Controller
{
    private const CATALOG_STATUS = 'Activo';
    private const DISPLAY_TYPES = ['Preventivo', 'Correctivo', 'Estético', 'Quirúrgico', 'Diagnóstico', 'Restaurativo', 'Emergencia'];
    private const VALID_TYPES = ['Preventivo', 'Correctivo', 'Estetico', 'Quirurgico', 'Diagnostico', 'Restaurativo', 'Emergencia', 'Estético', 'Quirúrgico', 'Diagnóstico', 'EstÃ©tico', 'QuirÃºrgico', 'DiagnÃ³stico'];

    public function create()
    {
        return view('pacientes.tratamiento', [
            'edit' => false,
            'tratamiento' => [],
            'tipos' => self::DISPLAY_TYPES,
        ]);
    }

    public function store(Request $request)
    {
        Treatment::create($this->validatedData($request) + [
            'paciente' => '',
            'fecha' => now()->toDateString(),
            'estado' => self::CATALOG_STATUS,
        ]);

        return redirect()->route('pacientes.tratamientos')->with('success', 'Tratamiento agregado al catalogo.');
    }

    public function index(Request $request)
    {
        $search = trim($request->query('search', ''));
        $tipo = $request->query('tipo', 'Todos');
        $order = $request->query('order', 'nombre_asc');

        $query = Treatment::query()
            ->whereNull('appointment_id')
            ->whereNull('patient_id');

        if ($search !== '') {
            $query->where(fn ($subQuery) => $subQuery
                ->where('tratamiento', 'like', "%{$search}%")
                ->orWhere('tipo', 'like', "%{$search}%")
                ->orWhere('descripcion', 'like', "%{$search}%"));
        }

        if ($tipo !== 'Todos' && $tipo !== '') {
            $query->whereIn('tipo', self::typeAliases($tipo));
        }

        $query = match ($order) {
            'nombre_desc' => $query->orderByDesc('tratamiento'),
            'costo_desc' => $query->orderByDesc('costo'),
            'costo_asc' => $query->orderBy('costo'),
            default => $query->orderBy('tratamiento'),
        };

        $tratamientos = $query->get()->map(fn (Treatment $treatment) => [
            'id' => $treatment->id,
            'tratamiento' => $treatment->tratamiento,
            'tipo' => $treatment->tipo,
            'costo' => $treatment->costo,
            'color' => $treatment->color ?: '#9700cf',
            'descripcion' => $treatment->descripcion,
        ]);

        $allTreatments = Treatment::query()
            ->whereNull('appointment_id')
            ->whereNull('patient_id')
            ->get();
        $resumen = [
            'total' => $allTreatments->count(),
            'tipos' => $allTreatments->pluck('tipo')->filter()->unique()->count(),
            'promedio' => $allTreatments->avg('costo') ?? 0,
        ];
        $tipos = collect(self::DISPLAY_TYPES)->sort()->values();

        return view('pacientes.tratamientos.index', compact('tratamientos', 'resumen', 'tipos'));
    }

    public function edit($id)
    {
        $treatment = Treatment::findOrFail($id);

        return view('pacientes.tratamiento', [
            'edit' => true,
            'tratamiento' => $treatment->only(['id', 'tratamiento', 'tipo', 'color', 'costo', 'descripcion']),
            'tipos' => self::DISPLAY_TYPES,
        ]);
    }

    public function update(Request $request, $id)
    {
        Treatment::findOrFail($id)->update($this->validatedData($request, $id));

        return redirect()->route('pacientes.tratamientos')->with('success', 'Tratamiento actualizado correctamente.');
    }

    public function destroy($id)
    {
        $treatment = Treatment::findOrFail($id);

        if ($this->isTreatmentInUse($treatment)) {
            return redirect()->route('pacientes.tratamientos')->withErrors([
                'tratamiento' => 'No puedes eliminar un tratamiento usado en citas, pacientes o reportes.',
            ]);
        }

        $treatment->delete();

        return redirect()->route('pacientes.tratamientos')->with('success', 'Tratamiento eliminado del catalogo.');
    }

    private function validatedData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'tratamiento' => [
                'required',
                'string',
                'max:255',
                Rule::unique('treatments', 'tratamiento')
                    ->whereNull('appointment_id')
                    ->whereNull('patient_id')
                    ->ignore($ignoreId),
            ],
            'tipo' => ['required', 'string', 'max:100', Rule::in(self::VALID_TYPES)],
            'color' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'costo' => 'required|numeric|min:0',
            'descripcion' => 'nullable|string|max:2000',
        ]);
    }

    private static function typeAliases(string $type): array
    {
        return match ($type) {
            'Estético' => ['Estético', 'Estetico', 'EstÃ©tico', 'EstÃƒÂ©tico'],
            'Quirúrgico' => ['Quirúrgico', 'Quirurgico', 'QuirÃºrgico', 'QuirÃƒÂºrgico'],
            'Diagnóstico' => ['Diagnóstico', 'Diagnostico', 'DiagnÃ³stico', 'DiagnÃƒÂ³stico'],
            default => [$type],
        };
    }

    private function isTreatmentInUse(Treatment $treatment): bool
    {
        return $treatment->patient_id !== null
            || $treatment->appointment_id !== null
            || $treatment->appointmentsUsingName()->exists();
    }
}
