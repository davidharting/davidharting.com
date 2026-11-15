<?php

use App\Models\User;
use App\Support\PreviewLoginLink;
use Tests\Support\ProcessEnvironment;
use Tests\TestCase;

/*
 * The login route is registered when the app boots, so each test reboots the
 * app in the environment it describes. Links are minted for PR #259, the
 * preview these tests pretend to be, and requested by path and query only.
 */

const PREVIEW_LOGIN_TEST_PR = 259;

/**
 * The path and query of a minted link, to request against the test app.
 */
function previewLoginPath(PreviewLoginLink $link): string
{
    return parse_url($link->url, PHP_URL_PATH).'?'.parse_url($link->url, PHP_URL_QUERY);
}

afterEach(fn () => ProcessEnvironment::restore());

describe('outside a PR preview', function () {
    test('the login link does not exist', function (array $environment) {
        /** @var TestCase $this */
        $this->rebootWithEnvironment($environment);
        config(['services.preview_login.key' => 'test-preview-key']);
        User::factory()->admin()->create(['email' => 'admin@example.com']);

        $this->get(previewLoginPath(PreviewLoginLink::mint(PREVIEW_LOGIN_TEST_PR)))->assertNotFound();

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
            'RENDER_SERVICE_NAME' => 'davidhartingdotcom-web-pr-'.PREVIEW_LOGIN_TEST_PR,
        ]);
        config(['services.preview_login.key' => 'test-preview-key']);
    });

    test('logs in as the seeded admin and lands on their landing page by default', function () {
        /** @var TestCase $this */
        $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);

        $this->get(previewLoginPath(PreviewLoginLink::mint(PREVIEW_LOGIN_TEST_PR)))->assertRedirect('/profile');

        $this->assertAuthenticatedAs($admin);
    });

    test('logs in as the named seeded user and goes to the requested page', function () {
        /** @var TestCase $this */
        $frodo = User::factory()->create(['email' => 'frodo@example.com']);

        $this->get(previewLoginPath(PreviewLoginLink::mint(PREVIEW_LOGIN_TEST_PR, 'frodo@example.com', '/media?type=book')))
            ->assertRedirect('/media?type=book');

        $this->assertAuthenticatedAs($frodo);
    });

    test('refuses a link that has been edited', function (string $parameter, string $value) {
        /** @var TestCase $this */
        User::factory()->create(['email' => 'frodo@example.com']);
        User::factory()->admin()->create(['email' => 'david@example.com']);
        parse_str(parse_url(PreviewLoginLink::mint(PREVIEW_LOGIN_TEST_PR, 'frodo@example.com', '/media')->url, PHP_URL_QUERY), $query);

        $query[$parameter] = $value;

        $this->get('/preview-login?'.http_build_query($query))->assertForbidden();
        $this->assertGuest();
    })->with([
        'different user' => ['as', 'david@example.com'],
        'different page' => ['to', '/admin'],
        'later expiry' => ['expires', (string) now()->addYear()->getTimestamp()],
        'forged signature' => ['signature', str_repeat('0', 64)],
    ]);

    test('refuses a link without a signature', function () {
        /** @var TestCase $this */
        User::factory()->admin()->create(['email' => 'admin@example.com']);

        $this->get('/preview-login?as=admin@example.com')->assertForbidden();

        $this->assertGuest();
    });

    test('refuses a link minted for another pull request', function () {
        /** @var TestCase $this */
        User::factory()->admin()->create(['email' => 'admin@example.com']);

        $this->get(previewLoginPath(PreviewLoginLink::mint(PREVIEW_LOGIN_TEST_PR + 1)))->assertForbidden();

        $this->assertGuest();
    });

    test('refuses a link once it has expired', function () {
        /** @var TestCase $this */
        User::factory()->admin()->create(['email' => 'admin@example.com']);
        $link = PreviewLoginLink::mint(PREVIEW_LOGIN_TEST_PR);

        $this->travel(PreviewLoginLink::LIFETIME_HOURS)->hours();
        $this->travel(1)->second();

        $this->get(previewLoginPath($link))->assertForbidden();
        $this->assertGuest();
    });

    test('refuses a link signed with a different key', function () {
        /** @var TestCase $this */
        User::factory()->admin()->create(['email' => 'admin@example.com']);
        $link = PreviewLoginLink::mint(PREVIEW_LOGIN_TEST_PR);

        config(['services.preview_login.key' => 'a-different-key']);

        $this->get(previewLoginPath($link))->assertForbidden();
        $this->assertGuest();
    });

    test('refuses every link when the preview has no key', function () {
        /** @var TestCase $this */
        User::factory()->admin()->create(['email' => 'admin@example.com']);
        $link = PreviewLoginLink::mint(PREVIEW_LOGIN_TEST_PR);

        config(['services.preview_login.key' => null]);

        $this->get(previewLoginPath($link))->assertForbidden();
        $this->assertGuest();
    });

    test('refuses a validly signed link for a seeded user who does not exist', function () {
        /** @var TestCase $this */
        $this->get(previewLoginPath(PreviewLoginLink::mint(PREVIEW_LOGIN_TEST_PR, 'nobody@example.com')))->assertNotFound();

        $this->assertGuest();
    });
});
