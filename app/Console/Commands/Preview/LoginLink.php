<?php

namespace App\Console\Commands\Preview;

use App\Support\PreviewLoginLink;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;

/**
 * Prints a signed link that logs into a PR preview as a seeded user and opens
 * a page there. Run it wherever PREVIEW_LOGIN_KEY is set. Threads get the same
 * link from the mint-preview-login-link MCP tool.
 */
class LoginLink extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'preview:login-link
            {pr : The pull request number whose preview to log into}
            {--as='.PreviewLoginLink::DEFAULT_EMAIL.' : The seeded @example.com user to log in as}
            {--to= : The path to open, e.g. /admin. Defaults to the user\'s landing page}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Print a signed one-tap login link for a PR preview';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $pullRequest = (string) $this->argument('pr');

        if (! ctype_digit($pullRequest)) {
            $this->components->error("[{$pullRequest}] is not a pull request number.");

            return self::FAILURE;
        }

        try {
            $link = PreviewLoginLink::mint((int) $pullRequest, (string) $this->option('as'), (string) $this->option('to'));
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line($link->url);

        return self::SUCCESS;
    }
}
