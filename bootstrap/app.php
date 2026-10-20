<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\StaffMiddleware;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureAdminSetupCompleted;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            headers: Request::HEADER_X_FORWARDED_FOR |
                Request::HEADER_X_FORWARDED_PORT |
                Request::HEADER_X_FORWARDED_PROTO |
                Request::HEADER_X_FORWARDED_AWS_ELB
        );

        $middleware->trustHosts();

        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'staff' => StaffMiddleware::class,
            'client' => \App\Http\Middleware\ClientMiddleware::class,
            'verified' => EnsureEmailIsVerified::class,
            'admin.setup' => EnsureAdminSetupCompleted::class,
        ]);

        $middleware->redirectUsersTo(function () {
            $user = auth()->user();
            if ($user?->isBootstrapAdmin()) {
                return route('admin.setup');
            }
            return match ($user?->role) {
                'admin' => route('admin.dashboard'),
                'staff' => route('staff.dashboard'),
                default => route('client.dashboard'),
            };
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
