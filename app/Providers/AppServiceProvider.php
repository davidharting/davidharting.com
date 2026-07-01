<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('administrate', function (User $user) {
            return $user->is_admin;
        });

        // Render blueprint yaml doesn't support env var interpolation in values,
        // so APP_URL is always set to the production domain in render.yaml.
        // Override it at runtime using Render's auto-injected RENDER_EXTERNAL_URL
        // so that PR preview environments get their actual onrender.com URL.
        if ($externalUrl = env('RENDER_EXTERNAL_URL')) {
            config(['app.url' => $externalUrl]);
        }
    }
}
