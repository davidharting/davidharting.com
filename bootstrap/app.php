<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/healthz',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Render terminates TLS, so the app only ever sees plain HTTP on $PORT.
        // Without this, url() and route() generate http:// links in production.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        // Never trim password fields — a leading or trailing space is part of
        // the secret.
        $middleware->trimStrings(except: [
            'current_password',
            'password',
            'password_confirmation',
        ]);

        $middleware->redirectTo(
            guests: fn () => route('login'),
            users: fn () => route('dashboard'),
        );

        // routes/api.php is not throttled unless we ask for it. The 'api'
        // limiter itself lives in AppServiceProvider::boot().
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
        ]);

        // Only the two participants may know monthly memories exist. A 405
        // for the wrong method would give the routes away, so it is a 404.
        $exceptions->map(fn (MethodNotAllowedHttpException $e) => request()->is('memories', 'memories/*')
            ? new NotFoundHttpException(previous: $e)
            : $e);
    })->create();
