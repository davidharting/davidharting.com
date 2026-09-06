<?php

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

describe('GET /healthz', function () {
    test('returns a health check response without touching session state', function () {
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

    test('reports down when a health check throws', function () {
        /** @var TestCase $this */
        Event::listen(DiagnosingHealth::class, function () {
            throw new RuntimeException('database unreachable');
        });

        $this->getJson('/healthz')
            ->assertStatus(500)
            ->assertJson(['status' => 'down']);
    });
});
