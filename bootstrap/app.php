<?php

use App\Http\Middleware\AddMcpOAuthChallengeHeader;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
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

        // routes/api.php is not throttled by default in this structure, unlike
        // the old Kernel's 'api' group. The limiter itself lives in
        // AppServiceProvider::boot().
        $middleware->throttleApi();

        // See the class docblock: global because it must wrap auth:api's 401.
        $middleware->append(AddMcpOAuthChallengeHeader::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
        ]);
    })->create();
