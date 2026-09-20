<?php

use App\Http\Middleware\AddMcpOAuthChallengeHeader;
use App\Http\Middleware\RestrictMcpConsentToAdmins;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;

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

        // See the class docblock: global because it must wrap auth:api's 401.
        $middleware->append(AddMcpOAuthChallengeHeader::class);

        /*
         * RestrictMcpConsentToAdmins is attached to Passport's route group via
         * config('passport.middleware'). Group middleware is not in the priority
         * list, so it sorts to the *front* of the stack — ahead of StartSession,
         * where $request->user() is always null and the middleware would wave
         * every request through. Pin it after the session instead.
         *
         * Feature tests cannot catch that on their own: actingAs() sets the user
         * on the guard directly, and both the guard and the session store are
         * container singletons that survive between requests inside one test
         * process, so a session-authenticated test passes either way. The
         * ordering is asserted directly in McpConsentAdminRestrictionTest.
         */
        $middleware->appendToPriorityList(
            after: StartSession::class,
            append: RestrictMcpConsentToAdmins::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
        ]);
    })->create();
