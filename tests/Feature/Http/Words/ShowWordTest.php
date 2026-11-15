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

test('shows the word, its numbered senses, its full definition and an Edit link', function () {
    /** @var TestCase $this */
    $word = Word::factory()->create([
        'word' => 'ephemeral',
        'short_definition' => 'lasting a very short time; fleeting',
        'full_definition' => "**adjective**\n\nlasting for only a short period",
    ]);

    $this->get('/words/ephemeral')
        ->assertSuccessful()
        ->assertSeeText('ephemeral')
        ->assertSeeInOrder(['<li>lasting a very short time</li>', '<li>fleeting</li>'], false)
        ->assertSeeHtml('<strong>adjective</strong>')
        ->assertSee(route('filament.admin.resources.words.edit', $word));
});

test('shows a single-sense short definition without numbering', function () {
    /** @var TestCase $this */
    Word::factory()->create(['word' => 'laconic', 'short_definition' => 'using few words', 'full_definition' => null]);

    $this->get('/words/laconic')
        ->assertSuccessful()
        ->assertSee('<p>using few words</p>', false)
        ->assertDontSee('<li>using few words</li>', false);
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
    $list = route('words.index', ['q' => 'eph', 'page' => 2]);

    $this->from($list)->get('/words/ephemeral')->assertSee('href="'.e($list).'"', false);
});

test('links back to the plain list when David arrived from elsewhere', function () {
    /** @var TestCase $this */
    Word::factory()->create(['word' => 'ephemeral']);

    $this->from(route('home'))->get('/words/ephemeral')->assertSee('href="'.route('words.index').'"', false);
});

test('shows the word under the site nav', function () {
    /** @var TestCase $this */
    Word::factory()->create(['word' => 'ephemeral']);

    $this->get('/words/ephemeral')->assertSuccessful()->assertSee('aria-label="Site"', false);
});
