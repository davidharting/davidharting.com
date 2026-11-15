<?php

use App\Http\Controllers\PreviewLoginController;
use Illuminate\Support\Facades\Route;

/*
 * Routes that only exist in PR preview environments. routes/web.php loads this
 * file only when DeploymentInfo::isPreview() is true, so none of these routes
 * are registered in production.
 *
 * The check runs when routes are registered, so caching routes during the
 * Docker build (which has no Render env vars) would drop these routes from
 * previews too. Nothing caches routes today.
 */

Route::get('/preview-login', PreviewLoginController::class)->name('preview.login');
