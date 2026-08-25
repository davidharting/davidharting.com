<?php

namespace App\Support;

/**
 * Identifies which deployment is serving the current request.
 *
 * Render injects the git and service variables into every instance, so these
 * values answer "which build of the site am I actually looking at?" — the
 * question that matters most in PR preview environments, where several
 * near-identical deployments are live at once.
 *
 * Read by both the `/whoareyou` Telegram command and the `/debug` page so the
 * two can never drift apart, including in the order they present.
 */
class DeploymentInfo
{
    /**
     * The facts identifying this deployment, in presentation order.
     *
     * Returned as an ordered list rather than a map because the order is part
     * of the contract: it is the reading order both surfaces render, and
     * defining it here is what keeps the page and the Telegram reply
     * recognisably the same output.
     *
     * Every fact is safe to show a logged-out visitor: the repository is
     * public, and the URL and service name are already visible in the address
     * bar of the request asking for them.
     *
     * @return list<DeploymentFact>
     */
    public static function facts(): array
    {
        $commit = self::shortCommit();

        return [
            new DeploymentFact('APP_URL', config('app.url')),
            new DeploymentFact('APP_ENV', config('app.env')),
            new DeploymentFact('IS_PULL_REQUEST', env('IS_PULL_REQUEST') ? 'yes' : 'no'),
            new DeploymentFact(
                'GIT_COMMIT',
                $commit ?? 'unknown',
                $commit ? "https://github.com/davidharting/davidharting.com/commit/{$commit}" : null,
            ),
            new DeploymentFact('GIT_BRANCH', env('RENDER_GIT_BRANCH') ?? 'unknown'),
            new DeploymentFact('SERVICE_NAME', env('RENDER_SERVICE_NAME') ?? 'unknown'),
        ];
    }

    /**
     * The abbreviated commit SHA this deployment was built from, or null when
     * running somewhere Render did not set it (local dev, tests, CI).
     */
    private static function shortCommit(): ?string
    {
        $commit = env('RENDER_GIT_COMMIT');

        return $commit ? substr($commit, 0, 10) : null;
    }
}
