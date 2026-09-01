<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePublicRegistrationIsEnabled
{
    /**
     * Gate the registration routes behind the `features.public_registration` flag.
     *
     * Aborts with a 404 rather than a 403 so a closed registration route is
     * indistinguishable from one that never existed.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('features.public_registration'), 404);

        return $next($request);
    }
}
