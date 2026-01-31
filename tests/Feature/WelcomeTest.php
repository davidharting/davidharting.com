<?php

use Tests\TestCase;

test('Welcome page contains link to atom feed', function () {
    /** @var TestCase $this */
    $response = $this->get('/');
    $response->assertStatus(200);

    $feedLink = '<link rel="alternate" type="application/atom+xml" href="http://davidharting-dot-com.test/feed" title="David Harting">';
    $response->assertSeeHtml($feedLink);
});

test('navigation links are present for anonymous users', function () {
    /** @var TestCase $this */
    $response = $this->get('/');
    $response->assertSuccessful();

    // Public nav links
    $response->assertSee('Home');
    $response->assertSee(route('home'));

    $response->assertSee('Notes');
    $response->assertSee(route('notes.index'));

    $response->assertSee('Media Log');
    $response->assertSee(route('media.index'));

    $response->assertSee('Pages');
    $response->assertSee(route('pages.index'));

    // Guest-only link
    $response->assertSee('Login');
    $response->assertSee(route('login'));

    // Admin link should not be visible
    $response->assertDontSee(route('admin.index'));
});
