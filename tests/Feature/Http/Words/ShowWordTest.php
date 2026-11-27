<?php

use App\Fsrs\Rating;
use App\Models\User;
use App\Models\Word;
use Illuminate\Support\Carbon;
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

test('links back to the list or search David came from, keeping its state', function (string $routeName, array $state) {
    /** @var TestCase $this */
    Word::factory()->create(['word' => 'ephemeral']);
    $cameFrom = route($routeName, $state);

    $this->get($cameFrom);

    $this->get('/words/ephemeral')->assertSee('href="'.e($cameFrom).'"', false);
})->with([
    'the list' => ['words.index', ['page' => 2, 'sort' => 'a_to_z']],
    'a search' => ['words.search', ['page' => 2, 'q' => 'eph']],
]);

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

test('shows the word\'s learning status', function () {
    /** @var TestCase $this */
    Word::factory()->troublesome()->create(['word' => 'liminal']);

    $this->get('/words/liminal')->assertSeeText('Troublesome');
});

test('says so when the word has not been practised yet', function () {
    /** @var TestCase $this */
    Word::factory()->create(['word' => 'liminal']);

    $this->get('/words/liminal')->assertSeeText('Not practised yet.');
});

test('lists each time the word was practised, newest first, with how often it was missed', function () {
    /** @var TestCase $this */
    $flashcard = Word::factory()->create(['word' => 'liminal'])->flashcard;
    $flashcard->grade(Rating::Again, Carbon::parse('2026-11-01 12:00'));
    $flashcard->grade(Rating::Good, Carbon::parse('2026-11-02 12:00'));
    $flashcard->grade(Rating::Again, Carbon::parse('2026-11-05 12:00'));

    $this->get('/words/liminal')
        ->assertSeeText('Practised 3 times, missed 2 times.')
        ->assertSeeTextInOrder([
            '5 November 2026', 'Missed it',
            '2 November 2026', 'Got it',
            '1 November 2026', 'Missed it',
        ]);
});
