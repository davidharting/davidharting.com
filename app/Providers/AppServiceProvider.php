<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /*
         * Switch off Passport's device authorization grant. Nothing here is a
         * device-flow client (MCP clients use authorization code + PKCE), and
         * with no user-code view bound, GET /oauth/device answered every
         * request with a 500.
         *
         * This must run in register(): Passport reads the flag when it
         * registers its routes during boot().
         *
         * To re-enable, delete this line, then bind the two views it needs with
         * Passport::deviceUserCodeView() and Passport::deviceAuthorizationView()
         * in boot(), and create a client with `php artisan passport:client --device`.
         *
         * @see https://github.com/davidharting/davidharting.com/issues/199
         */
        Passport::$deviceCodeGrantEnabled = false;
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * Passport defaults to storage_path(), which is runtime state the app
         * writes to, not configuration. Pinning one path here keeps every
         * environment loading keys the same way: dev and CI generate them with
         * `php artisan passport:keys`, and deployed environments will place the
         * Render secret files here.
         */
        Passport::loadKeysFrom(base_path('secrets/oauth'));

        // Passport ships no consent screen; laravel/mcp does.
        Passport::authorizationView('mcp::authorize');

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip()));

        Gate::define('administrate', function (User $user) {
            return $user->is_admin;
        });
    }
}
