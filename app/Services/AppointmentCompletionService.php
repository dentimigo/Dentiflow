<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ClinicSetting;
use App\Models\Treatment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AppointmentCompletionService
{
    public function complete(Appointment $appointment, ?Carbon $completionDate = null): bool
    {
        if ($appointment->estado === 'Cancelada') {
            return false;
        }

        if ($appointment->estado === 'Terminada' && Treatment::where('appointment_id', $appointment->id)->exists()) {
            return true;
        }

        $catalogTreatment = Treatment::query()
            ->whereNull('appointment_id')
            ->whereNull('patient_id')
            ->where('tratamiento', $appointment->tipo)
            ->first();

        if (! $catalogTreatment) {
            return false;
        }

        DB::transaction(function () use ($appointment, $catalogTreatment, $completionDate) {
            $adjustedCost = max(
                0,
                ((float) $catalogTreatment->costo + (float) $appointment->costo_extra)
                    * (1 - ((float) $appointment->descuento / 100))
            );
            $descriptionParts = array_filter([
                $catalogTreatment->descripcion,
                $appointment->completion_description
                    ? 'Descripción del descuento: '.$appointment->completion_description
                    : null,
                $appointment->procedimiento_extra
                    ? 'Procedimiento extra: '.$appointment->procedimiento_extra
                    : null,
                (float) $appointment->descuento > 0
                    ? 'Descuento aplicado: '.number_format((float) $appointment->descuento, 2).'%'
                    : null,
            ]);

            Treatment::updateOrCreate(
                ['appointment_id' => $appointment->id],
                [
                    'patient_id' => $appointment->patient_id,
                    'paciente' => $appointment->patient?->nombre ?? '',
                    'tratamiento' => $this->displayTreatmentName($catalogTreatment->tratamiento, $appointment->procedimiento_extra),
                    'tipo' => $catalogTreatment->tipo,
                    'fecha' => ($completionDate ?? now())->toDateString(),
                    'estado' => 'Realizado',
                    'costo' => $adjustedCost,
                    'descripcion' => implode(' | ', $descriptionParts) ?: null,
                ]
            );

            $appointment->update(['estado' => 'Terminada']);
        });

        return true;
    }

    public function completeOverdue(?int $dentistId = null, ?Carbon $now = null): int
    {
        $now ??= now();

        return Appointment::with(['patient'])
            ->whereNotIn('estado', ['Cancelada', 'Terminada'])
            ->when($dentistId, function ($query) use ($dentistId) {
                $query->where(function ($appointments) use ($dentistId) {
                    $appointments->where('dentist_id', $dentistId)
                        ->orWhereHas('participatingDentists', fn ($participants) => $participants->whereKey($dentistId));
                });
            })
            ->whereDate('date', '<=', $now->toDateString())
            ->orderBy('date')
            ->orderBy('time')
            ->get()
            ->filter(fn (Appointment $appointment) => $this->endedAt($appointment)->lessThanOrEqualTo($now))
            ->reduce(function (int $completed, Appointment $appointment) {
                return $this->complete($appointment, $appointment->date->copy()) ? $completed + 1 : $completed;
            }, 0);
    }

    public function endedAt(Appointment $appointment): Carbon
    {
        $date = $appointment->date->toDateString();

        if ($appointment->end_time) {
            return Carbon::parse("{$date} {$appointment->end_time}");
        }

        return Carbon::parse("{$date} {$appointment->time}")
            ->addMinutes(ClinicSetting::current()->appointment_duration);
    }

    private function displayTreatmentName(string $baseTreatment, ?string $extraTreatment): string
    {
        $extraTreatment = trim((string) $extraTreatment);

        return $extraTreatment === ''
            ? $baseTreatment
            : $baseTreatment.' + '.$extraTreatment;
    }
}
