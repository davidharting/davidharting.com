<?php

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/*
 * AppServiceProvider switches the device authorization grant off. These guard
 * against the flag moving into boot(), where it would run after Passport has
 * already registered the routes and silently do nothing.
 */

test('registers none of the device grant routes', function (string $name) {
    /** @var TestCase $this */
    expect(Route::has($name))->toBeFalse();
})->with([
    'passport.device',
    'passport.device.code',
    'passport.device.authorizations.authorize',
    'passport.device.authorizations.approve',
    'passport.device.authorizations.deny',
]);

test('answers the device user-code page with a 404 rather than a 500', function () {
    /** @var TestCase $this */
    $this->get('/oauth/device')->assertNotFound();
});

test('answers a device code request with a 404', function () {
    /** @var TestCase $this */
    $this->postJson('/oauth/device/code', ['client_id' => 'any'])->assertNotFound();
});

test('keeps the authorization code routes the MCP clients use', function (string $name) {
    /** @var TestCase $this */
    expect(Route::has($name))->toBeTrue();
})->with([
    'passport.authorizations.authorize',
    'passport.token',
]);
