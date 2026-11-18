<?php

use App\Models\User;
use App\Models\Word;
use Tests\TestCase;

beforeEach(function () {
    /** @var TestCase $this */
    $this->actingAs(User::factory()->admin()->create());
});

test('sends guests to log in', function () {
    /** @var TestCase $this */
    auth()->logout();
    $word = Word::factory()->create();

    $this->get(route('words.show', $word))->assertRedirect(route('login'));
});

test('is forbidden to anyone but David', function () {
    /** @var TestCase $this */
    $word = Word::factory()->create();

    $this->actingAs(User::factory()->create())->get(route('words.show', $word))->assertForbidden();
});

test('is not found for a word that is not on the list', function () {
    /** @var TestCase $this */
    $this->get('/words/nonexistent')->assertNotFound();
});

test('shows the word with both definitions and an Edit link', function () {
    /** @var TestCase $this */
    $word = Word::factory()->create([
        'word' => 'ephemeral',
        'short_definition' => 'lasting a very short time; fleeting',
        'full_definition' => "**adjective**\n\nlasting for only a short period",
    ]);

    $this->get('/words/ephemeral')
        ->assertSuccessful()
        ->assertSeeText('ephemeral')
        ->assertSeeText('lasting a very short time; fleeting')
        ->assertSeeHtml('<strong>adjective</strong>')
        ->assertSee(route('filament.admin.resources.words.edit', $word));
});

test('finds a word whatever the case of its address', function () {
    /** @var TestCase $this */
    Word::factory()->create(['word' => 'Ephemeral']);

    $this->get('/words/ephemeral')->assertSuccessful()->assertSeeText('Ephemeral');
});

test('finds words with spaces and slashes in them', function (string $spelling) {
    /** @var TestCase $this */
    $word = Word::factory()->create(['word' => $spelling]);

    $this->get(route('words.show', $word))->assertSuccessful()->assertSeeText($spelling);
})->with(['a priori', 'and/or']);

test('links back to the list David came from, keeping its search and page', function () {
    /** @var TestCase $this */
    Word::factory()->create(['word' => 'ephemeral']);
    $list = route('words.index', ['page' => 2, 'q' => 'eph']);

    $this->get($list);

    $this->get('/words/ephemeral')->assertSee('href="'.e($list).'"', false);
});

test('links back to the plain list when David arrived from elsewhere', function () {
    /** @var TestCase $this */
    Word::factory()->create(['word' => 'ephemeral']);

    $this->get(route('home'));

    $this->get('/words/ephemeral')->assertSee('href="'.route('words.index').'"', false);
});

test('turns the page forward when opened', function () {
    /** @var TestCase $this */
    Word::factory()->create(['word' => 'ephemeral']);

    $this->get('/words/ephemeral')->assertSee('data-transition-direction="forward"', false);
});
