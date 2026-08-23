<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Paga's server calls our webhook directly, not a browser with a
        // session, so it can never carry a CSRF token.
        $middleware->validateCsrfTokens(except: [
            'api/webhooks/paga/persistent-account',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
