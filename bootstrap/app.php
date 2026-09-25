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
        // Achter de Antagonist-proxy/HTTPS-terminatie: vertrouw de X-Forwarded-*-headers
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'core' => \App\Http\Middleware\CoreAuth::class,
            'rol' => \App\Http\Middleware\RolMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
