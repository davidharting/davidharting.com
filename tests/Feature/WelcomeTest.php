<?php

use Tests\TestCase;

test('Welcome page contains link to atom feed', function () {
    /** @var TestCase $this */
    $response = $this->get('/');
    $response->assertStatus(200);

    $feedLink = '<link rel="alternate" type="application/atom+xml" href="http://davidharting-dot-com.test/feed" title="David Harting">';
    $response->assertSeeHtml($feedLink);
});
