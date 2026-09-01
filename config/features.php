<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public User Registration
    |--------------------------------------------------------------------------
    |
    | Self-service registration at /register. Disabled: this site needs exactly
    | one account, and an open registration route became a real liability once
    | /mcp/admin started issuing OAuth tokens to any authenticated user. See
    | https://github.com/davidharting/davidharting.com/issues/181
    |
    | To re-open registration for walk-up features, set
    | FEATURE_PUBLIC_REGISTRATION=true. Nothing else needs to change: the
    | routes, controller, view, and tests are all still here and still covered.
    |
    */

    'public_registration' => env('FEATURE_PUBLIC_REGISTRATION', false),

];
