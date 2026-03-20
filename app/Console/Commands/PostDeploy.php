<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PostDeploy extends Command
{
    protected $signature = 'app:post-deploy';

    protected $description = 'Run post-deploy tasks: run migrations and configure Telegram';

    public function handle(): int
    {
        $this->info('Running migrations...');
        $this->call('migrate', ['--force' => true]);

        $webhookUrl = config('app.url').'/api/telegram/webhook';
        $this->info("Setting Telegram webhook to {$webhookUrl}...");
        $this->call('nutgram:hook:set', ['url' => $webhookUrl]);

        $this->info('Registering Telegram commands...');
        $this->call('nutgram:register-commands');

        $this->info('Post-deploy complete.');

        return self::SUCCESS;
    }
}
