<?php

use Tests\TestCase;

test('Welcome page contains link to atom feed', function () {
    /** @var TestCase $this */
    $response = $this->get('/');
    $response->assertStatus(200);

    $feedLink = '<link rel="alternate" type="application/atom+xml" href="http://davidharting-dot-com.test/feed" title="David Harting">';
    $response->assertSeeHtml($feedLink);
});

test('Welcome page is public and has swr headers', function () {
    /** @var TestCase $this */
    $response = $this->get('/');
    $response->assertStatus(200);

    $response->assertHeader(
        'Cache-Control',
        'max-age=60, public, stale-while-revalidate=3600'
    );

    $response->assertHeader('etag');
});
