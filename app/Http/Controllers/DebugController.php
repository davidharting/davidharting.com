<?php

namespace App\Http\Controllers;

use App\Support\DeploymentInfo;
use Illuminate\Http\Response;

class DebugController extends Controller
{
    /**
     * Show which deployment is serving this request.
     *
     * A public page that asks bots not to index it
     */
    public function __invoke(): Response
    {
        return response()
            ->view('debug', ['facts' => DeploymentInfo::facts()])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
