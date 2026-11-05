<?php

use App\Mcp\Servers\AdminServer;
use App\Mcp\Servers\PublicServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Http\Controllers\OAuthRegisterController;
use Laravel\Passport\Http\Middleware\CheckToken;

Mcp::web('/mcp', PublicServer::class)
    ->middleware('throttle:60,1');

Mcp::oauthRoutes();

// The package registers POST /oauth/register with no middleware. Registering the
// same method + URI again replaces that route, so this must stay after oauthRoutes().
// It keeps the package's route name, because laravel/mcp only advertises
// registration_endpoint in the OAuth metadata when mcp.oauth.register exists.
Route::post('oauth/register', OAuthRegisterController::class)
    ->middleware('throttle:10,1')
    ->name('mcp.oauth.register');

// CheckToken refuses admin-owned tokens that were not granted the mcp:use scope.
Mcp::web('/mcp/admin', AdminServer::class)
    ->middleware(['auth:api', CheckToken::using('mcp:use'), 'can:administrate', 'throttle:60,1']);
