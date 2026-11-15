<?php

use Tests\TestCase;

beforeEach(fn () => config(['services.preview_login.key' => 'test-preview-key']));

test('prints a signed login link for the pull request preview', function () {
    /** @var TestCase $this */
    $this->artisan('preview:login-link', ['pr' => '414', '--as' => 'frodo@example.com', '--to' => '/memories'])
        ->expectsOutputToContain('https://davidhartingdotcom-web-pr-414.onrender.com/preview-login?as=frodo%40example.com&to=%2Fmemories&expires=')
        ->assertSuccessful();
});

test('fails with the reason when the link cannot be minted', function () {
    /** @var TestCase $this */
    $this->artisan('preview:login-link', ['pr' => '414', '--as' => 'someone@gmail.com'])
        ->expectsOutputToContain('Only seeded @example.com users')
        ->assertFailed();
});

test('fails when the pull request is not a number', function () {
    /** @var TestCase $this */
    $this->artisan('preview:login-link', ['pr' => 'abc'])
        ->expectsOutputToContain('[abc] is not a pull request number.')
        ->assertFailed();
});
