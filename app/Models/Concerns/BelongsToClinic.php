<?php

namespace App\Models\Concerns;

use App\Models\Appointment;
use App\Models\Dentist;
use App\Models\Patient;
use App\Support\ClinicContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

trait BelongsToClinic
{
    protected static function bootBelongsToClinic(): void
    {
        static::addGlobalScope('clinic', function (Builder $query) {
            $query->where($query->qualifyColumn('clinic_workspace_id'), app(ClinicContext::class)->id());
        });

        static::saving(function ($record) {
            $workspaceId = app(ClinicContext::class)->id();

            if ($record->exists && (int) $record->getRawOriginal('clinic_workspace_id') !== $workspaceId) {
                throw ValidationException::withMessages(['clinic_workspace_id' => 'El registro pertenece a otro consultorio.']);
            }

            $record->clinic_workspace_id = $workspaceId;

            foreach ([
                'patient_id' => Patient::class,
                'dentist_id' => Dentist::class,
                'appointment_id' => Appointment::class,
            ] as $column => $related) {
                if ($record->isDirty($column) && $record->{$column} !== null
                    && ! $related::whereKey($record->{$column})->exists()) {
                    throw ValidationException::withMessages([$column => 'Selecciona un registro de este consultorio.']);
                }
            }
        });
    }
}
