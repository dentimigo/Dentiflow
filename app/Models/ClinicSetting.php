<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicSetting extends Model
{
    public const DEFAULTS = [
        'clinic_name' => 'Clinica DentiFlow',
        'support_email' => 'soporte@dentiflow.com',
        'appointment_duration' => 60,
        'schedule_interval' => 30,
        'opening_time' => '09:00',
        'closing_time' => '18:00',
        'default_appointment_status' => 'Pendiente',
        'automatic_reminders_enabled' => true,
        'inventory_alerts_enabled' => true,
        'maintenance_mode_enabled' => false,
        'administrative_audit_enabled' => true,
        'assistant_allowed_sections' => ['inicio', 'calendario', 'citas', 'pacientes', 'expedientes', 'pacientes.tratamientos', 'inventario'],
    ];

    protected $fillable = [
        'clinic_name',
        'support_email',
        'appointment_duration',
        'schedule_interval',
        'opening_time',
        'closing_time',
        'default_appointment_status',
        'automatic_reminders_enabled',
        'inventory_alerts_enabled',
        'maintenance_mode_enabled',
        'administrative_audit_enabled',
        'assistant_allowed_sections',
    ];

    protected $casts = [
        'appointment_duration' => 'integer',
        'schedule_interval' => 'integer',
        'automatic_reminders_enabled' => 'boolean',
        'inventory_alerts_enabled' => 'boolean',
        'maintenance_mode_enabled' => 'boolean',
        'administrative_audit_enabled' => 'boolean',
        'assistant_allowed_sections' => 'array',
    ];

    public const ASSISTANT_SECTION_OPTIONS = [
        'inicio' => [
            'label' => 'Resumen',
            'detail' => 'Vista general de agenda y actividad diaria.',
            'routes' => ['inicio'],
        ],
        'calendario' => [
            'label' => 'Calendario',
            'detail' => 'Consultar agenda, disponibilidad y días no disponibles.',
            'routes' => ['calendario*'],
        ],
        'citas' => [
            'label' => 'Citas',
            'detail' => 'Crear, editar, confirmar y dar seguimiento a citas.',
            'routes' => ['citas*'],
        ],
        'pacientes' => [
            'label' => 'Pacientes',
            'detail' => 'Registrar y actualizar datos de pacientes.',
            'routes' => [
                'pacientes',
                'pacientes.create',
                'pacientes.store',
                'pacientes.edit',
                'pacientes.update',
                'pacientes.delete',
                'pacientes.cancel',
                'pacientes.historial',
                'pacientes.expediente',
                'pacientes.clinical.store',
                'pacientes.medical.update',
                'pacientes.historial.destroy',
                'pacientes.tratamiento',
                'pacientes.tratamientos*',
                'pacientes.destroy',
            ],
        ],
        'expedientes' => [
            'label' => 'Expedientes',
            'detail' => 'Consultar expedientes clínicos de pacientes.',
            'routes' => ['expedientes*', 'pacientes.expediente'],
        ],
        'pacientes.tratamientos' => [
            'label' => 'Tratamientos',
            'detail' => 'Administrar catálogo, colores y costos de tratamientos.',
            'routes' => ['pacientes.tratamiento', 'pacientes.tratamientos*'],
        ],
        'inventario' => [
            'label' => 'Inventario',
            'detail' => 'Gestionar materiales, existencias y alertas.',
            'routes' => ['inventario*'],
        ],
    ];

    public static function current(): self
    {
        return self::query()->first() ?? new self(self::DEFAULTS);
    }

    public function assistantAllowedSections(): array
    {
        $sections = $this->assistant_allowed_sections ?: self::DEFAULTS['assistant_allowed_sections'];

        return collect($sections)
            ->filter(fn ($section) => array_key_exists($section, self::ASSISTANT_SECTION_OPTIONS))
            ->values()
            ->all();
    }

    public function assistantAllowedRoutes(): array
    {
        return collect($this->assistantAllowedSections())
            ->flatMap(fn ($section) => self::ASSISTANT_SECTION_OPTIONS[$section]['routes'])
            ->merge(['perfil*', 'logout'])
            ->unique()
            ->values()
            ->all();
    }

    public static function upsertCurrent(array $data): self
    {
        $settings = self::query()->first() ?? new self();

        // La aplicacion maneja una sola configuracion global para toda la clinica.
        $settings->fill($data);
        $settings->save();

        return $settings;
    }
}
