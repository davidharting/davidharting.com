<?php

namespace App\Http\Controllers;

use App\Support\DeploymentInfo;
use Illuminate\Http\Response;

class DebugController extends Controller
{
    /**
     * Show which deployment is serving this request.
     *
     * Deliberately public: the page is most useful in a PR preview
     * environment, which has its own database and session cookie, so gating it
     * behind a login would make it unreachable exactly when it is wanted.
     * Everything on it is already public — see {@see DeploymentInfo::facts()}.
     * It is kept out of search results by robots.txt, a `noindex` meta tag,
     * and the `X-Robots-Tag` header below.
     */
    public function __invoke(): Response
    {
        return response()
            ->view('debug', ['facts' => DeploymentInfo::facts()])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
