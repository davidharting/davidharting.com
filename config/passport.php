<?php

use App\Http\Middleware\RestrictMcpConsentToAdmins;

/*
 * Passport merges this file over the package default (PassportServiceProvider
 * calls mergeConfigFrom), so only the keys we actually override belong here —
 * everything left out keeps tracking the package. Do not publish the full file.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Passport Route Middleware
    |--------------------------------------------------------------------------
    |
    | Applied by PassportServiceProvider::registerRoutes() to every route
    | Passport registers. This is the only seam for the consent-time admin
    | check; see the middleware's docblock and issue #186.
    |
    */

    'middleware' => [
        RestrictMcpConsentToAdmins::class,
    ],

];
