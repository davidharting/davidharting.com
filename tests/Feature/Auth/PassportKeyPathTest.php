<?php

use Laravel\Passport\Passport;
use Tests\TestCase;

/*
 * Passport falls back to storage_path() when no path is pinned, and that
 * fallback is silent — a developer with stale keys under storage/ would not
 * notice AppServiceProvider's loadKeysFrom() call going missing, while CI and
 * deployed environments would fail with "Invalid key supplied".
 */

test('keys resolve from secrets/oauth rather than Passport\'s storage default', function () {
    /** @var TestCase $this */
    expect(Passport::keyPath('oauth-private.key'))->toBe(base_path('secrets/oauth/oauth-private.key'));

    expect(Passport::keyPath('oauth-private.key'))->not->toBe(storage_path('oauth-private.key'));
});
