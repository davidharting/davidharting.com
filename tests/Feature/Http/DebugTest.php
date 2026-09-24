<?php

use Tests\Support\RenderedHead;
use Tests\TestCase;

describe('GET /debug', function () {
    test('shows the values identifying this deployment', function () {
        /** @var TestCase $this */
        $response = $this->get('/debug');

        $response->assertOk();
        $response->assertSeeTextInOrder([
            'APP_URL',
            'http://davidharting-dot-com.test',
            'APP_ENV',
            'testing',
            'IS_PULL_REQUEST',
            'no',
            'GIT_COMMIT',
            'abcdef1234',
            'GIT_BRANCH',
            'my-feature-branch',
            'SERVICE_NAME',
            'davidhartingdotcom-web-pr-999',
        ]);
        $response->assertSee('https://github.com/davidharting/davidharting.com/commit/abcdef1234');
    });

    test('is reachable without logging in', function () {
        /** @var TestCase $this */
        $this->assertGuest();

        $this->get('/debug')->assertOk();
    });

    test('tells robots not to index it', function () {
        /** @var TestCase $this */
        $response = $this->get('/debug');

        expect(RenderedHead::from($response)->meta('robots'))->toBe('noindex, nofollow')
            ->and($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow');
    });
});
