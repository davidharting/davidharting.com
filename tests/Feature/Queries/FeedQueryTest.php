<?php

use Tests\TestCase;

test('empty feed', function () {
    /** @var TestCase $this */
    $response = $this->get('/feed');

    $response->assertStatus(200);
    $response->assertSeeTextInOrder([
        'Notes and media log updates from David Harting'
    ]);
});

test('Invisible posts do not show up', function () {
    /** @var TestCase $this */
    $this->fail('Not implemented yet');
});


test('Posts are in reverse chronological order', function () {
    /** @var TestCase $this */
    $this->fail('Not implemented yet');
});
