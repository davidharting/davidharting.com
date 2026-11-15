<?php

use App\Support\PreviewLoginLink;
use Tests\TestCase;

beforeEach(fn () => config(['services.preview_login.key' => 'test-preview-key']));

describe('mint()', function () {
    test('links to the login page on the pull request preview', function () {
        /** @var TestCase $this */
        $this->freezeTime();

        $link = PreviewLoginLink::mint(414, 'Frodo@Example.com', '/media?type=book');

        parse_str(parse_url($link->url, PHP_URL_QUERY), $query);
        expect($link->url)->toStartWith('https://davidhartingdotcom-web-pr-414.onrender.com/preview-login?')
            ->and($query)->toMatchArray([
                'as' => 'frodo@example.com',
                'to' => '/media?type=book',
                'expires' => (string) now()->addHours(24)->getTimestamp(),
            ])
            ->and($query['signature'])->toMatch('/^[0-9a-f]{64}$/')
            ->and($link->expiresAt->getTimestamp())->toBe(now()->addHours(24)->getTimestamp());
    });

    test('leaves the destination out when none is given', function () {
        /** @var TestCase $this */
        parse_str(parse_url(PreviewLoginLink::mint(414)->url, PHP_URL_QUERY), $query);

        expect($query)->not->toHaveKey('to')
            ->and($query['as'])->toBe('admin@example.com');
    });

    test('refuses a user outside the seeded example.com domain', function () {
        /** @var TestCase $this */
        PreviewLoginLink::mint(414, 'someone@gmail.com');
    })->throws(InvalidArgumentException::class, 'Only seeded @example.com users');

    test('refuses a destination that is not a path on the site', function (string $to) {
        /** @var TestCase $this */
        PreviewLoginLink::mint(414, 'admin@example.com', $to);
    })->throws(InvalidArgumentException::class, 'The destination must be a path')->with([
        'absolute URL' => 'https://evil.test/phish',
        'protocol-relative URL' => '//evil.test/phish',
        'backslash trick' => '/\\evil.test/phish',
        'relative path without a leading slash' => 'evil.test',
        'header injection' => "/admin\r\nLocation: https://evil.test",
        'unencoded space' => '/media?q=two words',
    ]);

    test('refuses a pull request number below 1', function () {
        /** @var TestCase $this */
        PreviewLoginLink::mint(0);
    })->throws(InvalidArgumentException::class, '[0] is not a pull request number.');

    test('refuses to sign without a key', function () {
        /** @var TestCase $this */
        config(['services.preview_login.key' => null]);

        PreviewLoginLink::mint(414);
    })->throws(RuntimeException::class, 'PREVIEW_LOGIN_KEY is not set');
});
