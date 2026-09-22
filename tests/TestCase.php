<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Make a request, then forget scoped container instances -- simulating
     * what production does between requests.
     *
     * Production runs on Octane, which boots the app once and serves many
     * requests from it. Anything bound with `scoped()` is meant to live for one
     * request only, so Octane forgets those instances after every request. The
     * test layer does not: every request in a test shares one application, and
     * nothing resets scoped state between them. Without this override, state a
     * controller writes during one request leaks into the next request in the
     * same test -- behavior production never exhibits. The concrete case is
     * Laravel Head's `CurrentHead`: a note's `Head::title()` would reappear as
     * the title of whatever page the test requests next.
     *
     * How we know production does this:
     *
     * - config/octane.php registers Octane's FlushTemporaryContainerInstances
     *   listener on OperationTerminated. That listener calls
     *   forgetScopedInstances(), and Octane's RequestTerminated event
     *   implements OperationTerminated, so it runs after every request.
     * - Verified against the PR #206 preview, which runs the same FrankenPHP
     *   Octane setup as production: a note request alternated with /notes and
     *   /pages twenty times, and every page rendered its own title.
     *
     * If that listener is ever removed from config/octane.php, this simulation
     * stops matching production and should be revisited.
     *
     * Flushing *after* the request, as Octane does, rather than before keeps
     * any scoped state a test sets up ahead of its first request.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null): TestResponse
    {
        try {
            return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
        } finally {
            $this->app->forgetScopedInstances();
        }
    }
}
