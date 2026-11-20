<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\Dentist;
use App\Models\InventoryItem;
use App\Models\Treatment;
use App\Models\User;
use App\Support\ClinicContext;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(ClinicContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        View::composer('layouts.app', function ($view) {
            $role = session('access_role');
            $notifications = collect();

            if ($role === 'doctor') {
                $user = User::find(session('access_user_id'));
                $dentist = $user
                    ? Dentist::where('user_id', $user->id)->first()
                    : null;

                if ($dentist) {
                    $highlightedAppointments = Appointment::with('patient')
                        ->where('dentist_id', $dentist->id)
                        ->where('reminder_sent', true)
                        ->whereDate('date', '>=', today())
                        ->whereNotIn('estado', ['Cancelada', 'Realizada', 'Terminada'])
                        ->orderBy('date')
                        ->orderBy('time')
                        ->limit(3)
                        ->get()
                        ->map(fn (Appointment $appointment) => [
                            'title' => 'Cita destacada',
                            'detail' => ($appointment->patient?->nombre ?? 'Paciente').' · '.$appointment->date->format('d/m').' '.substr((string) $appointment->time, 0, 5),
                            'url' => route('citas.show', $appointment->id),
                            'type' => 'appointment_highlight',
                        ]);

                    $notifications = Appointment::with('patient')
                        ->where('dentist_id', $dentist->id)
                        ->whereDate('date', today())
                        ->whereNotIn('estado', ['Cancelada', 'Realizada'])
                        ->where('reminder_sent', false)
                        ->orderBy('time')
                        ->limit(4)
                        ->get()
                        ->map(fn (Appointment $appointment) => [
                            'title' => 'Cita de '.$appointment->patient?->nombre,
                            'detail' => substr((string) $appointment->time, 0, 5).' · '.$appointment->estado,
                            'url' => route('citas.show', $appointment->id),
                            'type' => 'appointment',
                        ]);

                    $notifications = collect($highlightedAppointments)->merge(collect($notifications))->take(5);

                    $pendingTreatments = Treatment::whereHas(
                        'appointment',
                        fn ($query) => $query->where('dentist_id', $dentist->id)
                    )->where('estado', 'Activo')->count();

                    if ($pendingTreatments > 0) {
                        $notifications->push([
                            'title' => $pendingTreatments.' tratamientos activos',
                            'detail' => 'Revisa el seguimiento clínico pendiente.',
                            'url' => route('pacientes.tratamientos'),
                            'type' => 'treatment',
                        ]);
                    }
                }
            }

            if (in_array($role, ['asistente', 'admin'], true)) {
                $highlightedAppointments = Appointment::with('patient')
                    ->where('reminder_sent', true)
                    ->whereDate('date', '>=', today())
                    ->whereNotIn('estado', ['Cancelada', 'Realizada', 'Terminada'])
                    ->orderBy('date')
                    ->orderBy('time')
                    ->limit(3)
                    ->get()
                    ->map(fn (Appointment $appointment) => [
                        'title' => 'Cita destacada',
                        'detail' => ($appointment->patient?->nombre ?? 'Paciente').' · '.$appointment->date->format('d/m').' '.substr((string) $appointment->time, 0, 5),
                        'url' => route('citas.show', $appointment->id),
                        'type' => 'appointment_highlight',
                    ]);

                $notifications = Appointment::with('patient')
                    ->whereIn('estado', ['Pendiente', 'Por confirmar'])
                    ->where('reminder_sent', false)
                    ->whereDate('date', '>=', today())
                    ->orderBy('date')
                    ->orderBy('time')
                    ->limit(4)
                    ->get()
                    ->map(fn (Appointment $appointment) => [
                        'title' => 'Cita por confirmar',
                        'detail' => ($appointment->patient?->nombre ?? 'Paciente').' · '.$appointment->date->format('d/m').' '.substr((string) $appointment->time, 0, 5),
                        'url' => route('citas.show', $appointment->id),
                        'type' => 'appointment',
                    ]);

                $notifications = collect($highlightedAppointments)->merge(collect($notifications))->take(5);

                $stockAlerts = InventoryItem::query()
                    ->where('stock', '<=', InventoryItem::STOCK_RUNNING_OUT_MAX)
                    ->orderBy('stock')
                    ->orderBy('material')
                    ->limit(5)
                    ->get();

                foreach ($stockAlerts as $stockItem) {
                    $notifications->push([
                        'title' => $stockItem->stockStatusLabel().': '.$stockItem->material,
                        'detail' => $stockItem->stock.' unidades disponibles',
                        'url' => route('inventario'),
                        'type' => 'inventory',
                    ]);
                }
            }

            $view->with('roleNotifications', $notifications->values());
        });
    }

    private function currentAssistantUserId(): ?int
    {
        $userId = session('access_user_id');

        if (session('access_role') !== 'asistente' || ! $userId) {
            return null;
        }

        return app(ClinicContext::class)->assistants()->whereKey($userId)->exists()
            ? (int) $userId
            : null;
    }
}
