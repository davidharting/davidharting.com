<?php

use App\Support\PreviewLoginLink;
use Tests\TestCase;

beforeEach(fn () => config(['services.preview_login.key' => 'test-preview-key']));

describe('mint()', function () {
    test('links to the login page on the pull request preview', function () {
        /** @var TestCase $this */
        $this->freezeTime();

        $link = PreviewLoginLink::mint(414, 'Frodo@Example.com', '/media?type=book');

        parse_str(parse_url($link, PHP_URL_QUERY), $query);
        expect($link)->toStartWith('https://davidhartingdotcom-web-pr-414.onrender.com/preview-login?')
            ->and($query)->toMatchArray([
                'as' => 'frodo@example.com',
                'to' => '/media?type=book',
                'expires' => (string) now()->addHours(24)->getTimestamp(),
            ])
            ->and($query['signature'])->toMatch('/^[0-9a-f]{64}$/');
    });

    test('leaves the destination out when none is given', function () {
        parse_str(parse_url(PreviewLoginLink::mint(414), PHP_URL_QUERY), $query);

        expect($query)->not->toHaveKey('to')
            ->and($query['as'])->toBe('admin@example.com');
    });

    test('refuses a user outside the seeded example.com domain', function () {
        PreviewLoginLink::mint(414, 'someone@gmail.com');
    })->throws(InvalidArgumentException::class, 'Only seeded @example.com users');

    test('refuses a destination that is not a path on the site', function (string $to) {
        PreviewLoginLink::mint(414, 'admin@example.com', $to);
    })->throws(InvalidArgumentException::class, 'The destination must be a path')->with([
        'absolute URL' => 'https://evil.test/phish',
        'protocol-relative URL' => '//evil.test/phish',
        'backslash trick' => '/\\evil.test/phish',
        'relative path without a leading slash' => 'evil.test',
        'header injection' => "/admin\r\nLocation: https://evil.test",
    ]);

    test('refuses to sign without a key', function () {
        config(['services.preview_login.key' => null]);

        PreviewLoginLink::mint(414);
    })->throws(RuntimeException::class, 'PREVIEW_LOGIN_KEY is not set');
});
