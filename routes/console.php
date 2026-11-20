<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Services\AppointmentCompletionService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('appointments:complete-overdue', function (AppointmentCompletionService $completionService) {
    $completed = $completionService->completeOverdue();

    $this->info("Citas terminadas automaticamente: {$completed}");
})->purpose('Mark overdue appointments as completed and register their earnings');

Artisan::command('dentiflow:provision-admins', function () {
    $accounts = [];
    foreach (config('provisioning') as $role => $credentials) {
        $email = Str::lower(trim((string) $credentials['email']));
        $password = $credentials['password'];
        if ($email === '' && ($password === null || $password === '')) {
            continue;
        }

        $valid = Validator::make(['email' => $email, 'password' => $password], [
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:12',
        ])->passes();

        if (! $valid || strlen($password) > 72 || isset($accounts[$email])) {
            $this->error("Revisa las variables privadas de {$role}. Usa un correo distinto por cuenta y una contraseña de 12 a 72 bytes.");

            return 1;
        }

        $accounts[$email] = ['name' => $credentials['name'], 'role' => $role, 'password' => $password];
    }

    return DB::transaction(function () use ($accounts) {
        foreach ($accounts as $email => $attributes) {
            $existing = User::where('email', $email)->first();
            if ($existing && $existing->role !== $attributes['role']) {
                $this->error('Un correo configurado pertenece a una cuenta con otro rol. Usa un correo distinto.');

                return 1;
            }
        }

        foreach ($accounts as $email => $attributes) {
            User::firstOrCreate(['email' => $email], $attributes);
        }

        return 0;
    });
})->purpose('Crea las cuentas administrativas iniciales configuradas para DentiFlow');

Artisan::command('market:analyze', function () {
    $file = new SplFileObject(database_path('market/endutih-2024.csv'));
    $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
    $file->setCsvControl(';', '"', '');

    $rates = [];
    $scopes = ['Nacional', 'Urbano', 'Rural'];

    foreach ($file as $index => $row) {
        if ($index === 0 || $row === false) {
            continue;
        }

        [$scope, $rawRate] = array_pad($row, 2, '');
        $scope = ucfirst(strtolower(trim((string) $scope)));
        $rate = str_replace(',', '.', trim((string) $rawRate));

        if (count($row) !== 2 || ! in_array($scope, $scopes, true) || isset($rates[$scope])
            || ! is_numeric($rate) || (float) $rate < 0 || (float) $rate > 100) {
            $this->error('Fila inválida en el CSV de mercado: '.($index + 1));

            return 1;
        }

        $rates[$scope] = (float) $rate;
    }

    if (count($rates) !== count($scopes)) {
        $this->error('El CSV debe incluir datos nacionales, urbanos y rurales.');

        return 1;
    }

    $this->info('ENDUTIH 2024 · Personas de 6 años y más que usaron internet');
    $this->table(['Ámbito', 'Porcentaje'], array_map(
        fn ($scope) => [$scope, number_format($rates[$scope], 1, ',', '').' %'],
        $scopes,
    ));
    $gap = number_format($rates['Urbano'] - $rates['Rural'], 1, ',', '');
    $this->line("Brecha urbano-rural: {$gap} puntos porcentuales");

    return 0;
})->purpose('Procesa y muestra los indicadores de conectividad del estudio de mercado');
