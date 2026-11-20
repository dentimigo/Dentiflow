<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            \Illuminate\Support\Facades\Route::post('/stripe/webhook', \App\Http\Controllers\StripeWebhookController::class)
                ->name('stripe.webhook');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Railway termina HTTPS en su proxy y reenvía la petición a Laravel.
        // Confiar en el proxy permite que Laravel genere assets y formularios HTTPS.
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            \App\Http\Middleware\ResolveClinicWorkspace::class,
            \App\Http\Middleware\EnsureRoleAccess::class,
        ]);
        $middleware->prependToPriorityList(
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\ResolveClinicWorkspace::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TokenMismatchException $exception, Request $request) {
            if ($request->is('logout')) {
                return redirect()->route('role.select');
            }

            return redirect()
                ->route('role.select')
                ->with('warning', 'Tu sesion expiro. Vuelve a seleccionar tu perfil e intenta de nuevo.');
        });

        $exceptions->respond(function (Response $response, \Throwable $exception, Request $request) {
            if ($response->getStatusCode() !== 419) {
                return $response;
            }

            if ($request->is('logout')) {
                return redirect()->route('role.select');
            }

            return redirect()
                ->route('login')
                ->with('warning', 'Tu sesion expiro. Recarga el formulario e intenta iniciar sesion de nuevo.');
        });
    })->create();
