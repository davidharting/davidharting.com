<?php

use Tests\TestCase;

test('kitchen sink page renders successfully', function () {
    /** @var TestCase $this */
    $response = $this->get('/kitchen-sink');

    $response->assertStatus(200);
    $response->assertSee('Kitchen Sink');
});

test('kitchen sink page has noindex meta tag', function () {
    /** @var TestCase $this */
    $response = $this->get('/kitchen-sink');

    $response->assertStatus(200);
    $response->assertSee('<meta name="robots" content="noindex, nofollow" />', false);
});
