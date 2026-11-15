<?php

use App\Models\User;
use Tests\Support\ProcessEnvironment;
use Tests\TestCase;

/*
 * The login route is registered when the app boots, so each test reboots the
 * app in the environment it describes.
 */

afterEach(fn () => ProcessEnvironment::restore());

describe('outside a PR preview', function () {
    test('the login link does not exist', function (array $environment) {
        /** @var TestCase $this */
        $this->rebootWithEnvironment($environment);
        User::factory()->admin()->create(['email' => 'admin@example.com']);

        $this->get('/preview-login')->assertNotFound();

        $this->assertGuest();
    })->with([
        'production' => [['IS_PULL_REQUEST' => 'false', 'RENDER_SERVICE_NAME' => 'davidhartingdotcom-web']],
        'only the pull request flag set' => [['IS_PULL_REQUEST' => 'true', 'RENDER_SERVICE_NAME' => 'davidhartingdotcom-web']],
    ]);
});

describe('in a PR preview', function () {
    beforeEach(function () {
        /** @var TestCase $this */
        $this->rebootWithEnvironment([
            'IS_PULL_REQUEST' => 'true',
            'RENDER_SERVICE_NAME' => 'davidhartingdotcom-web-pr-259',
        ]);
    });

    test('logs in as the seeded admin and lands on their landing page by default', function () {
        /** @var TestCase $this */
        $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);

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

    test('matches the email whatever its case', function () {
        /** @var TestCase $this */
        $david = User::factory()->create(['email' => 'david@example.com']);

        $this->get('/preview-login?as=David@Example.com')->assertRedirect();

        $this->assertAuthenticatedAs($david);
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

    test('ignores an unsafe destination and lands on the user landing page', function (string $to) {
        /** @var TestCase $this */
        User::factory()->admin()->create(['email' => 'admin@example.com']);

        $this->get('/preview-login?to='.urlencode($to))->assertRedirect('/profile');
    })->with([
        'absolute URL' => 'https://evil.test/phish',
        'protocol-relative URL' => '//evil.test/phish',
        'backslash trick' => '/\\evil.test/phish',
        'relative path without a leading slash' => 'evil.test',
        'header injection' => "/admin\r\nLocation: https://evil.test",
    ]);
});
