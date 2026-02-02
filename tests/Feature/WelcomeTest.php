<?php

use App\Models\User;
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
    $response->assertSeeText('Home');
    $response->assertSee(route('home'));

    $response->assertSeeText('Notes');
    $response->assertSee(route('notes.index'));

    $response->assertSeeText('Media Log');
    $response->assertSee(route('media.index'));

    $response->assertSeeText('Pages');
    $response->assertSee(route('pages.index'));

    // Guest-only link
    $response->assertSeeText('Login');
    $response->assertSee(route('login'));

    // Admin link should not be visible
    $response->assertDontSee(route('admin.index'));
});

test('admin link is visible to admin users', function () {
    /** @var TestCase $this */
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get('/');
    $response->assertSuccessful();

    $response->assertSeeText('Admin');
    $response->assertSee(route('admin.index'));

    // Login link should not be visible when authenticated
    $response->assertDontSeeText('Login');
});
