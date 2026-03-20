<?php

use Illuminate\Support\Facades\Http;

test('runs migrations and configures telegram', function () {
    /** @var TestCase $this */
    Http::fake();

    $this->artisan('app:post-deploy')
        ->expectsOutputToContain('Running migrations')
        ->expectsOutputToContain('Setting Telegram webhook')
        ->expectsOutputToContain('Registering Telegram commands')
        ->expectsOutputToContain('Post-deploy complete')
        ->assertSuccessful();
});
