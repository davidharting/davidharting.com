<?php

use App\Models\User;
use Tests\Support\ProcessEnvironment;
use Tests\TestCase;

/*
 * Routes are registered when the app boots, so each test sets the environment
 * and then boots a fresh app to get the routes that environment would get.
 */

afterEach(fn () => ProcessEnvironment::restore());

describe('outside a PR preview', function () {
    test('the login link does not exist in production', function () {
        /** @var TestCase $this */
        ProcessEnvironment::set([
            'IS_PULL_REQUEST' => 'false',
            'RENDER_SERVICE_NAME' => 'davidhartingdotcom-web',
        ]);
        $this->refreshApplication();
        $this->beginDatabaseTransaction();
        User::factory()->create(['email' => 'admin@example.com', 'is_admin' => true]);

        $this->get('/preview-login')->assertNotFound();

        $this->assertGuest();
    });

    test('the login link does not exist when only the pull request flag is set', function () {
        /** @var TestCase $this */
        ProcessEnvironment::set([
            'IS_PULL_REQUEST' => 'true',
            'RENDER_SERVICE_NAME' => 'davidhartingdotcom-web',
        ]);
        $this->refreshApplication();
        $this->beginDatabaseTransaction();
        User::factory()->create(['email' => 'admin@example.com', 'is_admin' => true]);

        $this->get('/preview-login')->assertNotFound();

        $this->assertGuest();
    });
});

describe('in a PR preview', function () {
    beforeEach(function () {
        /** @var TestCase $this */
        ProcessEnvironment::set([
            'IS_PULL_REQUEST' => 'true',
            'RENDER_SERVICE_NAME' => 'davidhartingdotcom-web-pr-259',
        ]);
        $this->refreshApplication();
        $this->beginDatabaseTransaction();
    });

    test('logs in as the seeded admin and lands on their landing page by default', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['email' => 'admin@example.com', 'is_admin' => true]);

        $this->get('/preview-login')->assertRedirect('/profile');

        $this->assertAuthenticatedAs($admin);
    });

    test('logs in as the named seeded user and goes to the requested page', function () {
        /** @var TestCase $this */
        $frodo = User::factory()->create(['email' => 'frodo@example.com']);

        $this->get('/preview-login?as=frodo@example.com&to='.urlencode('/media?type=book'))
            ->assertRedirect('/media?type=book');

        $this->assertAuthenticatedAs($frodo);
    });

    test('refuses to log in as a user outside the seeded example.com domain', function () {
        /** @var TestCase $this */
        User::factory()->create(['email' => 'someone@gmail.com']);

        $this->get('/preview-login?as=someone@gmail.com')->assertNotFound();

        $this->assertGuest();
    });

    test('refuses a seeded email that has no user', function () {
        /** @var TestCase $this */
        $this->get('/preview-login?as=nobody@example.com')->assertNotFound();

        $this->assertGuest();
    });

    test('ignores a destination on another site and lands on the user landing page', function (string $to) {
        /** @var TestCase $this */
        User::factory()->create(['email' => 'admin@example.com', 'is_admin' => true]);

        $this->get('/preview-login?to='.urlencode($to))->assertRedirect('/profile');
    })->with([
        'absolute URL' => 'https://evil.test/phish',
        'protocol-relative URL' => '//evil.test/phish',
        'backslash trick' => '/\\evil.test/phish',
        'relative path without a leading slash' => 'evil.test',
        'header injection' => "/admin\r\nLocation: https://evil.test",
    ]);
});
