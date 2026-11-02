<?php

use App\Exceptions\CustomerApiException;
use App\Http\Middleware\EnsureStaffRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(__DIR__.'/../routes/channels.php', [
        'middleware' => ['api', 'auth:staff_api'],
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'staff.role' => EnsureStaffRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (CustomerApiException $exception, Request $request) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => $exception->errorCode,
                'errors' => (object) $exception->errors,
            ], $exception->status);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
