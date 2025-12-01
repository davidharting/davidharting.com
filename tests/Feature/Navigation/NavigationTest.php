<?php

use Tests\TestCase;

test('pages link appears in navigation', function () {
    /** @var TestCase $this */
    $response = $this->get('/');
    $response->assertSuccessful();
    $response->assertSee('Pages');
    $response->assertSee(route('pages.index'));
});
