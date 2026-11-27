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

    $this->get('/words/search?q=canny')->assertRedirect(route('login'));
});

test('is forbidden to anyone but David', function () {
    /** @var TestCase $this */
    $this->actingAs(User::factory()->create())->get('/words/search?q=canny')->assertForbidden();
});

test('sends a blank search back to the list, keeping the page size', function () {
    /** @var TestCase $this */
    $this->get('/words/search?q=++&per_page=2')->assertRedirect(route('words.index', ['per_page' => 2]));
});

test('searches the word and both definitions, ignoring case', function () {
    /** @var TestCase $this */
    Word::factory()->create(['word' => 'Fleetwood', 'short_definition' => 'a band']);
    Word::factory()->create(['word' => 'ephemeral', 'short_definition' => 'FLEETING']);
    Word::factory()->create(['word' => 'evanescent', 'short_definition' => 'vanishing', 'full_definition' => 'soon gone; fleeting']);
    Word::factory()->create(['word' => 'laconic', 'short_definition' => 'using few words', 'full_definition' => null]);

    $this->get('/words/search?q=fleet')
        ->assertSuccessful()
        ->assertSeeText('3 matches for “fleet”', false)
        ->assertSeeText('Fleetwood')
        ->assertSeeText('ephemeral')
        ->assertSeeText('evanescent')
        ->assertDontSeeText('laconic');
});

test('lists spelling matches under Words, then definition matches under Found in definitions', function () {
    /** @var TestCase $this */
    Word::factory()->create(['word' => 'astute', 'short_definition' => 'canny; sharp']);
    Word::factory()->create(['word' => 'uncanny', 'short_definition' => 'eerie']);
    Word::factory()->create(['word' => 'canny', 'short_definition' => 'shrewd']);

    $this->get('/words/search?q=canny')
        ->assertSuccessful()
        // Starting after the page's own title and Practise button, and
        // checking definitions, since the words appear in the header too.
        ->assertSeeTextInOrder(['Practise', 'Words', 'shrewd', 'eerie', 'Found in definitions', 'canny; sharp']);
});

test('has no status filters or sorts', function () {
    /** @var TestCase $this */
    Word::factory()->create(['word' => 'canny']);

    $this->get('/words/search?q=canny')
        ->assertSeeText('canny')
        ->assertDontSee('aria-label="Filter by learning status"', false)
        ->assertDontSee('aria-label="Sort"', false);
});

test('clears a search back to the plain list, keeping the page size', function () {
    /** @var TestCase $this */
    $this->get('/words/search?q=canny&per_page=2')
        ->assertSee('href="'.route('words.index', ['per_page' => 2]).'"', false);
});

test('keeps the search and page size in the page links', function () {
    /** @var TestCase $this */
    Word::factory()->count(2)->sequence(['word' => 'canny'], ['word' => 'uncanny'])->create();

    $this->get('/words/search?q=can&per_page=1')
        ->assertSee(route('words.search', ['q' => 'can', 'per_page' => 1, 'page' => 2]));
});

test('matches a percent sign literally', function () {
    /** @var TestCase $this */
    Word::factory()->create(['word' => 'percentile', 'short_definition' => 'a value below which 50% fall']);
    Word::factory()->create(['word' => 'laconic', 'short_definition' => 'using few words']);

    $this->get('/words/search?q='.urlencode('%'))
        ->assertSeeText('1 match for')
        ->assertSeeText('percentile')
        ->assertDontSeeText('laconic');
});

test('says so when nothing matches', function () {
    /** @var TestCase $this */
    Word::factory()->create(['word' => 'ephemeral']);

    $this->get('/words/search?q=zzz')->assertSeeText('No words match “zzz”.', false);
});

test('refuses a search longer than 255 characters', function () {
    /** @var TestCase $this */
    $this->get('/words/search?q='.str_repeat('a', 256))->assertSessionHasErrors('q');
});

test('keeps /words/search for searching, even for a word spelled "search"', function () {
    /** @var TestCase $this */
    Word::factory()->create(['word' => 'search']);

    $this->get('/words/search')->assertRedirect(route('words.index'));
});
