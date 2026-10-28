<?php

use App\Models\User;
use Database\Seeders\MemorySeeder;
use Tests\Support\RenderedHead;
use Tests\TestCase;

beforeEach(function () {
    /** @var TestCase $this */
    $this->travelTo('2026-10-18T12:00:00Z');
    $this->seed(MemorySeeder::class);
    $this->actingAs(User::firstWhere('email', 'katie@example.com'));
});

test('names the home screen app Memories on every memories page', function (string $url) {
    /** @var TestCase $this */
    $head = RenderedHead::from($this->get($url)->assertSuccessful());

    expect($head->link('manifest'))->toBe('/memories-manifest.json')
        ->and($head->meta('apple-mobile-web-app-title'))->toBe('Memories')
        ->and($head->meta('application-name'))->toBe('Memories');
})->with([
    '/memories',
    '/memories/2026-09',
    '/memories/2026-09/write',
    '/memories/earlier',
]);

test('keeps the site manifest everywhere else', function () {
    /** @var TestCase $this */
    $head = RenderedHead::from($this->get('/'));

    expect($head->link('manifest'))->toBe('/manifest.json')
        ->and($head->meta('apple-mobile-web-app-title'))->toBe('David Harting');
});

test('the memories manifest opens straight into memories in the page colours', function () {
    $manifest = json_decode(file_get_contents(public_path('memories-manifest.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest)->toMatchArray([
        'name' => 'Memories',
        'short_name' => 'Memories',
        'start_url' => '/memories',
        'scope' => '/memories',
        'display' => 'standalone',
        'background_color' => '#f8f1e3',
        'theme_color' => '#f8f1e3',
    ]);
});
