<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureStaffPasswordIsCurrent::class);
        // Middleware aliases
        $middleware->alias([
            'acess_checker' => \App\Http\Middleware\AccessChecker::class,
            'onelogin' => \App\Http\Middleware\OneLogin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Long, file-upload-heavy forms (e.g. discipline incident reports) can outlive the
        // session/CSRF token. Without this, a timed-out submission falls through to Laravel's
        // generic, unstyled 419 page instead of a message the user recognizes as an error.
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) {
            if (!$request->expectsJson()) {
                return redirect()->back()
                    ->with('error', 'Your session expired. Please try submitting again.')
                    ->withInput();
            }
        });
    })->create();
