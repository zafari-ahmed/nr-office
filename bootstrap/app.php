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
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);
        // "Log in as" tabs get their own session and run as the employee (see App\Models\Impersonation).
        $middleware->web(prepend: [\App\Http\Middleware\UseImpersonationSession::class]);
        $middleware->web(append: [\App\Http\Middleware\Impersonate::class, \App\Http\Middleware\EnsureModuleEnabled::class]);
        $middleware->prependToPriorityList(before: \Illuminate\Session\Middleware\StartSession::class, prepend: \App\Http\Middleware\UseImpersonationSession::class);
        $middleware->prependToPriorityList(before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class, prepend: \App\Http\Middleware\Impersonate::class);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
