<?php

use Tests\TestCase;

describe('GET /healthz', function () {
    test('returns a lightweight health check response without touching session state', function () {
        /** @var TestCase $this */
        $this->assertDatabaseCount('sessions', 0);

        $response = $this->get('/healthz');

        $response->assertOk();
        $response->assertSeeText('OK');
        expect($response->headers->get('Set-Cookie'))->toBeNull();
        $this->assertDatabaseCount('sessions', 0);
    });
});
