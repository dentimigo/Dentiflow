<?php

namespace Tests\Unit;

use App\Models\ClinicSetting;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;

class ClinicSettingAssistantRoutesTest extends TestCase
{
    public function test_assistant_routes_cover_patient_subpages_when_patients_section_is_enabled(): void
    {
        $settings = new ClinicSetting([
            'assistant_allowed_sections' => ['pacientes'],
        ]);

        $routes = $settings->assistantAllowedRoutes();

        $this->assertTrue(Str::is($routes, 'pacientes.expediente'));
        $this->assertTrue(Str::is($routes, 'pacientes.delete'));
        $this->assertTrue(Str::is($routes, 'pacientes.clinical.store'));
    }
}
