<?php

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/*
 * Proxy trust is configured in bootstrap/app.php via $middleware->trustProxies(),
 * so this exercises the configured global stack rather than instantiating a
 * middleware class. That is the point: it now tests what the app actually runs.
 */

test('trusts Render forwarded scheme headers without trusting forwarded host', function () {
    /** @var TestCase $this */
    Route::get('/test/proxy-echo', fn () => response()->json([
        'secure' => request()->isSecure(),
        'port' => request()->getPort(),
        'host' => request()->getHost(),
    ]));

    $response = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->getJson('/test/proxy-echo', [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Port' => '443',
            // Not trusted: honouring this would let a caller forge the host used
            // in generated URLs, password-reset links included.
            'X-Forwarded-Host' => 'attacker.example',
        ]);

    $response->assertOk();
    $response->assertJsonPath('secure', true);
    $response->assertJsonPath('port', 443);
    // Asserted as a negative rather than against a fixed hostname, which varies
    // with APP_URL: the property that matters is that the forged host is ignored.
    expect($response->json('host'))->not->toBe('attacker.example');
});
