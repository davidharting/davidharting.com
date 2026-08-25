<?php

use Tests\TestCase;

/**
 * `public/robots.txt` is a static file served by Caddy/FrankenPHP, so it never
 * reaches Laravel's router — `$this->get('/robots.txt')` returns 404 here and
 * would prove nothing. Reading the file is the honest assertion.
 */
describe('robots.txt', function () {
    test('keeps the debug page out of search results', function () {
        /** @var TestCase $this */
        expect(file_get_contents(public_path('robots.txt')))->toContain('Disallow: /debug');
    });
});
