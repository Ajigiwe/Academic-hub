<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminOnly::class,
            'sandbox' => \App\Http\Middleware\SandboxGatewayOnly::class,
        ]);

        // Provider & deployment webhooks are server-to-server and carry their own
        // signatures / tokens — CSRF tokens don't apply to them.
        $middleware->validateCsrfTokens(except: [
            'payments/webhook',
            'deploy/webhook',
        ]);

        // Keep admin routes and deploy webhook reachable during maintenance
        $middleware->preventRequestsDuringMaintenance(except: [
            'admin/*',
            'deploy/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
