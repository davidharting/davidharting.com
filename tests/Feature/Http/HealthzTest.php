<?php

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * The route is Laravel's built-in health check, registered via
 * withRouting(health: ...) in bootstrap/app.php.
 */
describe('GET /healthz', function () {
    /**
     * Makes the route report unhealthy: the route catches whatever a
     * DiagnosingHealth listener throws and turns it into a 500.
     */
    $failAHealthCheck = function (): void {
        Event::listen(DiagnosingHealth::class, function () {
            throw new RuntimeException('database unreachable');
        });
    };

    test('returns 200 without touching session state', function () {
        /** @var TestCase $this */
        $this->assertDatabaseCount('sessions', 0);

        $response = $this->get('/healthz');

        $response->assertOk();
        expect($response->headers->get('Set-Cookie'))->toBeNull();
        $this->assertDatabaseCount('sessions', 0);
    });

    test('dispatches DiagnosingHealth so listeners can add checks', function () {
        /** @var TestCase $this */
        Event::fake([DiagnosingHealth::class]);

        $this->get('/healthz')->assertOk();

        Event::assertDispatched(DiagnosingHealth::class);
    });

    test('returns 500 when a health check throws', function () use ($failAHealthCheck) {
        /** @var TestCase $this */
        $failAHealthCheck();

        $this->get('/healthz')->assertStatus(500);
    });

    /**
     * The route negotiates on Accept, not on health: it answers HTML to a
     * browser and JSON to a client that asks for it, whether up or down.
     */
    test('answers HTML to a browser and JSON on request', function () use ($failAHealthCheck) {
        /** @var TestCase $this */
        $this->get('/healthz')->assertSeeText('Application up');
        $this->getJson('/healthz')->assertOk()->assertExactJson(['status' => 'up']);

        $failAHealthCheck();

        $this->get('/healthz')->assertSeeText('Application experiencing problems');
        $this->getJson('/healthz')->assertStatus(500)->assertExactJson(['status' => 'down']);
    });
});
