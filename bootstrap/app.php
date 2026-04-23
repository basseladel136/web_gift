<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php', // 👈 ضيف دي
        commands: __DIR__ . '/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'is_admin'   => \App\Http\Middleware\IsAdmin::class,
            'set_locale' => \App\Http\Middleware\SetLocale::class,
        ]);

        // Apply locale middleware to all API routes
        $middleware->appendToGroup('api', \App\Http\Middleware\SetLocale::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
