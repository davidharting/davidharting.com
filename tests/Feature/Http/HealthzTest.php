<?php

use Tests\TestCase;

describe('GET /healthz', function () {
    test('returns 200 OK', function () {
        /** @var TestCase $this */
        $response = $this->get('/healthz');

        $response->assertOk();
        $response->assertSeeText('OK');
    });

    test('does not set any session cookie', function () {
        /** @var TestCase $this */
        $response = $this->get('/healthz');

        $response->assertOk();
        expect($response->headers->get('Set-Cookie'))->toBeNull();
    });

    test('does not write a session row', function () {
        /** @var TestCase $this */
        $this->assertDatabaseCount('sessions', 0);

        $this->get('/healthz')->assertOk();

        $this->assertDatabaseCount('sessions', 0);
    });
});
