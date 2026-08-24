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
 * two can never drift apart.
 */
class DeploymentInfo
{
    /**
     * The deployment's identifying values, keyed by the environment variable
     * they come from, in display order.
     *
     * Every value is safe to show a logged-out visitor: the repository is
     * public, and the URL and service name are already visible in the address
     * bar of the request asking for them.
     *
     * @return array<string, string>
     */
    public static function values(): array
    {
        return [
            'APP_URL' => config('app.url'),
            'APP_ENV' => config('app.env'),
            'IS_PULL_REQUEST' => env('IS_PULL_REQUEST') ? 'yes' : 'no',
            'GIT_COMMIT' => self::shortCommit() ?? 'unknown',
            'GIT_BRANCH' => env('RENDER_GIT_BRANCH') ?? 'unknown',
            'SERVICE_NAME' => env('RENDER_SERVICE_NAME') ?? 'unknown',
        ];
    }

    /**
     * The abbreviated commit SHA this deployment was built from, or null when
     * running somewhere Render did not set it (local dev, tests, CI).
     */
    public static function shortCommit(): ?string
    {
        $commit = env('RENDER_GIT_COMMIT');

        return $commit ? substr($commit, 0, 10) : null;
    }

    /**
     * A GitHub link to the deployed commit, or null when the commit is unknown.
     */
    public static function commitUrl(): ?string
    {
        $commit = self::shortCommit();

        if (! $commit) {
            return null;
        }

        return "https://github.com/davidharting/davidharting.com/commit/{$commit}";
    }
}
