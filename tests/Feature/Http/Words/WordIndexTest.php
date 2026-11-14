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

    $this->get('/words')->assertRedirect(route('login'));
});

test('is forbidden to anyone but David', function () {
    /** @var TestCase $this */
    $this->actingAs(User::factory()->create())->get('/words')->assertForbidden();
});

test('says so when there are no words yet', function () {
    /** @var TestCase $this */
    $this->get('/words')->assertSuccessful()->assertSeeText('No words yet');
});

test('lists the newest words first, each with its short definition', function () {
    /** @var TestCase $this */
    $this->travelTo('2026-11-01');
    Word::factory()->create(['word' => 'laconic', 'short_definition' => 'using few words']);
    $this->travelTo('2026-11-02');
    Word::factory()->create(['word' => 'ephemeral', 'short_definition' => 'lasting a very short time']);

    $this->get('/words')
        ->assertSuccessful()
        ->assertSeeText('2 words')
        ->assertSeeTextInOrder(['ephemeral', 'lasting a very short time', 'laconic', 'using few words']);
});

test('lists words A to Z under dictionary letter headings, ignoring case', function () {
    /** @var TestCase $this */
    Word::factory()->create(['word' => 'Laconic']);
    Word::factory()->create(['word' => 'ephemeral']);
    Word::factory()->create(['word' => 'lambent']);

    $html = $this->get('/words?sort=a_to_z')->assertSuccessful()->getContent();

    expect(preg_replace('/\s+/', ' ', $html))
        ->toMatch('#id="letter-0"[^>]*> E </h2>.*ephemeral.*id="letter-1"[^>]*> L </h2>.*Laconic.*lambent#');
});

test('shows no letter headings when listing the newest first', function () {
    /** @var TestCase $this */
    Word::factory()->create(['word' => 'ephemeral']);

    $this->get('/words')->assertDontSee('id="letter-', false);
});

test('searches the word and both definitions, ignoring case', function () {
    /** @var TestCase $this */
    Word::factory()->create(['word' => 'Fleetwood', 'short_definition' => 'a band']);
    Word::factory()->create(['word' => 'ephemeral', 'short_definition' => 'FLEETING']);
    Word::factory()->create(['word' => 'evanescent', 'short_definition' => 'vanishing', 'full_definition' => 'soon gone; fleeting']);
    Word::factory()->create(['word' => 'laconic', 'short_definition' => 'using few words', 'full_definition' => null]);

    $this->get('/words?q=fleet')
        ->assertSuccessful()
        ->assertSeeText('3 matches for “fleet”', false)
        ->assertSeeText('Fleetwood')
        ->assertSeeText('ephemeral')
        ->assertSeeText('evanescent')
        ->assertDontSeeText('laconic');
});

test('matches a percent sign in a search literally', function () {
    /** @var TestCase $this */
    Word::factory()->create(['word' => 'percentile', 'short_definition' => 'a value below which 50% fall']);
    Word::factory()->create(['word' => 'laconic', 'short_definition' => 'using few words']);

    $this->get('/words?q='.urlencode('%'))
        ->assertSeeText('1 match for')
        ->assertSeeText('percentile')
        ->assertDontSeeText('laconic');
});

test('says so when nothing matches a search', function () {
    /** @var TestCase $this */
    Word::factory()->create(['word' => 'ephemeral']);

    $this->get('/words?q=zzz')->assertSeeText('No words match “zzz”.', false);
});

test('shows 250 words a page by default', function () {
    /** @var TestCase $this */
    // Created directly: the factory's unique fake words run out before 251.
    foreach (range(1, 251) as $number) {
        Word::create(['word' => sprintf('word%03d', $number), 'short_definition' => 'a word']);
    }

    $this->get('/words?sort=a_to_z')
        ->assertSeeText('Page 1 of 2')
        ->assertSeeText('word250')
        ->assertDontSeeText('word251');
});

test('pages by a chosen page size, keeping the search, sort and size in every link', function () {
    /** @var TestCase $this */
    Word::factory()
        ->count(3)
        ->sequence(fn ($sequence) => ['word' => 'word'.($sequence->index + 1)])
        ->create();
    $state = ['q' => 'word', 'sort' => 'a_to_z', 'per_page' => 2];

    $this->get(route('words.index', $state))
        ->assertSeeText('Page 1 of 2')
        ->assertSeeText('word2')
        ->assertDontSeeText('word3')
        ->assertSee(route('words.index', [...$state, 'page' => 2]))
        ->assertSee(route('words.index', ['q' => 'word', 'per_page' => 2]))
        ->assertSee('<input type="hidden" name="per_page" value="2" />', false);

    $this->get(route('words.index', [...$state, 'page' => 2]))
        ->assertSeeText('word3')
        ->assertDontSeeText('word2');
});

test('refuses a sort it does not know', function (string $query) {
    /** @var TestCase $this */
    $this->get('/words?'.$query)->assertSessionHasErrors('sort');
})->with(['an unknown name' => 'sort=nonsense', 'a list' => 'sort[]=a_to_z']);

test('refuses a page size above 1000', function () {
    /** @var TestCase $this */
    $this->get('/words?per_page=1001')->assertSessionHasErrors('per_page');
});

test('offers a way back from a page past the end', function () {
    /** @var TestCase $this */
    Word::factory()->create(['word' => 'ephemeral']);

    $this->get('/words?page=5')
        ->assertSuccessful()
        ->assertSeeText('No words on this page.')
        ->assertSee(route('words.index'));
});

test('refuses a page number too large to count to', function () {
    /** @var TestCase $this */
    Word::factory()->create(['word' => 'ephemeral']);

    $this->get('/words?page=99999999999999999999999')->assertSessionHasErrors('page');
});
