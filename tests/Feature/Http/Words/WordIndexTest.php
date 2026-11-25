<?php

use App\Fsrs\Rating;
use App\Models\User;
use App\Models\Word;
use Illuminate\Testing\TestResponse;
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

test('lists the newest words first, each with its short definition and a link to it', function () {
    /** @var TestCase $this */
    $this->travelTo('2026-11-01');
    $older = Word::factory()->create(['word' => 'laconic', 'short_definition' => 'using few words']);
    $this->travelTo('2026-11-02');
    Word::factory()->create(['word' => 'ephemeral', 'short_definition' => 'lasting a very short time']);

    $this->get('/words')
        ->assertSuccessful()
        ->assertSeeText('2 words')
        ->assertSeeTextInOrder(['ephemeral', 'lasting a very short time', 'laconic', 'using few words'])
        ->assertSee(route('words.show', $older));
});

test('lists words A to Z under dictionary letter headings, ignoring case', function () {
    /** @var TestCase $this */
    Word::factory()->create(['word' => 'Laconic']);
    Word::factory()->create(['word' => 'ephemeral']);
    Word::factory()->create(['word' => 'lambent']);

    $html = $this->get('/words?sort=a_to_z')->assertSuccessful()->getContent();

    expect(preg_replace('/\s+/', ' ', $html))
        ->toMatch('#<h2[^>]*>E</h2>.*ephemeral.*<h2[^>]*>L</h2>.*Laconic.*lambent#');
});

test('shows no letter headings when listing the newest first', function () {
    /** @var TestCase $this */
    Word::factory()->create(['word' => 'ephemeral']);

    $this->get('/words')->assertDontSee('>E</h2>', false);
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

test('lists a search\'s spelling matches under Words, then definition matches under Found in definitions', function () {
    /** @var TestCase $this */
    Word::factory()->create(['word' => 'astute', 'short_definition' => 'canny; sharp']);
    Word::factory()->create(['word' => 'uncanny', 'short_definition' => 'eerie']);
    Word::factory()->create(['word' => 'canny', 'short_definition' => 'shrewd']);

    $this->get('/words?q=canny')
        ->assertSuccessful()
        // Starting after the page's own title and Practise button, and
        // checking definitions, since the words appear in the header too.
        ->assertSeeTextInOrder(['Practise', 'Words', 'shrewd', 'eerie', 'Found in definitions', 'canny; sharp']);
});

test('shows no letter headings in an A to Z search', function () {
    /** @var TestCase $this */
    Word::factory()->create(['word' => 'canny']);

    $this->get('/words?sort=a_to_z')->assertSee('>C</h2>', false);
    $this->get('/words?q=canny&sort=a_to_z')->assertDontSee('>C</h2>', false);
});

test('makes a search its own mode, without the status filters or sorts', function () {
    /** @var TestCase $this */
    Word::factory()->create(['word' => 'canny']);

    $this->get('/words')
        ->assertSee('aria-label="Filter by learning status"', false)
        ->assertSee('aria-label="Sort"', false);

    $this->get('/words?q=canny')
        ->assertSeeText('canny')
        ->assertDontSee('aria-label="Filter by learning status"', false)
        ->assertDontSee('aria-label="Sort"', false);
});

test('ignores a status filter and sort while searching', function () {
    /** @var TestCase $this */
    Word::factory()->create(['word' => 'cantankerous', 'short_definition' => 'grumpy']);
    Word::factory()->troublesome()->create(['word' => 'cant', 'short_definition' => 'hypocritical talk']);

    // Best match order, with the word that isn't troublesome still listed.
    $this->get('/words?q=cant&status=troublesome&sort=recent')
        ->assertSeeText('2 matches')
        ->assertSeeTextInOrder(['hypocritical talk', 'grumpy']);
});

test('clears a search back to the plain list', function () {
    /** @var TestCase $this */
    $this->get('/words?q=canny&sort=recent')->assertSee('href="'.route('words.index').'"', false);
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

test('pages by a chosen page size', function () {
    /** @var TestCase $this */
    Word::factory()
        ->count(3)
        ->sequence(fn ($sequence) => ['word' => 'word'.($sequence->index + 1)])
        ->create();

    $this->get('/words?sort=a_to_z&per_page=2')
        ->assertSeeText('Page 1 of 2')
        ->assertSeeText('word2')
        ->assertDontSeeText('word3');

    $this->get('/words?sort=a_to_z&per_page=2&page=2')
        ->assertSeeText('word3')
        ->assertDontSeeText('word2');
});

test('keeps the search and page size in the page links', function () {
    /** @var TestCase $this */
    Word::factory()
        ->count(3)
        ->sequence(fn ($sequence) => ['word' => 'word'.($sequence->index + 1)])
        ->create();

    $this->get(route('words.index', ['q' => 'word', 'per_page' => 2]))
        ->assertSee(route('words.index', ['q' => 'word', 'per_page' => 2, 'page' => 2]));
});

test('keeps the sort and page size in the page and sort links', function () {
    /** @var TestCase $this */
    Word::factory()
        ->count(3)
        ->sequence(fn ($sequence) => ['word' => 'word'.($sequence->index + 1)])
        ->create();
    $state = ['sort' => 'a_to_z', 'per_page' => 2];

    $response = $this->get(route('words.index', $state));

    // The Next page link.
    $response->assertSee(route('words.index', [...$state, 'page' => 2]));
    // The Recent sort link, which drops only the sort.
    $response->assertSee(route('words.index', ['per_page' => 2]));
});

test('starts a new search at its best matches, whatever the sort before', function () {
    /** @var TestCase $this */
    // The search box keeps the page size but not the sort.
    $this->get('/words?sort=a_to_z&per_page=2')
        ->assertSee('name="per_page" value="2"', false)
        ->assertDontSee('name="sort"', false);
});

test('refuses a sort it does not know', function (string $query) {
    /** @var TestCase $this */
    $this->get('/words?'.$query)->assertSessionHasErrors('sort');
})->with(['an unknown name' => 'sort=nonsense', 'a list' => 'sort[]=a_to_z']);

test('accepts a page size up to 1000', function () {
    /** @var TestCase $this */
    $this->get('/words?per_page=1000')->assertSuccessful();
});

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
        ->assertSeeText('Back to the first page');
});

test('refuses a page number too large to count to', function () {
    /** @var TestCase $this */
    Word::factory()->create(['word' => 'ephemeral']);

    $this->get('/words?page=99999999999999999999999')->assertSessionHasErrors('page');
});

test('links to practice with how many words are due', function () {
    /** @var TestCase $this */
    Word::factory()->count(2)->create();
    Word::factory()->create()->flashcard->grade(Rating::Good);

    $this->get('/words')
        ->assertSee(route('words.practice'))
        ->assertSeeText('2 due');
});

test('says practice is caught up when nothing is due', function () {
    /** @var TestCase $this */
    Word::factory()->create()->flashcard->grade(Rating::Good);

    $this->get('/words')->assertSeeText('caught up')->assertDontSeeText('due');
});

test('turns the page back when coming back from a word', function () {
    /** @var TestCase $this */
    $word = Word::factory()->create(['word' => 'ephemeral']);

    $this->get(route('words.show', $word));

    $this->get('/words')->assertSee('data-transition-direction="back"', false);
});

test('does not turn the page when moving within the list', function () {
    /** @var TestCase $this */
    $this->get('/words');

    $this->get('/words?sort=a_to_z')->assertDontSee('data-transition-direction', false);
});

/**
 * Assert the page has a status chip linking to $url, labelled $label with
 * $count beside it.
 */
function assertChip(TestResponse $response, string $label, int $count, string $url): void
{
    $chip = sprintf('#<a\s[^>]*href="%s"[^>]*>\s*%s\s*<span[^>]*>%d</span>#', preg_quote(e($url), '#'), $label, $count);

    expect($response->getContent())->toMatch($chip);
}

describe('learning status chips', function () {
    test('show how many words have each status, and a total', function () {
        /** @var TestCase $this */
        Word::factory()->count(2)->create();
        Word::factory()->troublesome()->create();
        Word::factory()->count(3)->mastered()->create();

        $response = $this->get('/words')->assertSuccessful();

        assertChip($response, 'All', 6, route('words.index'));
        assertChip($response, 'New', 2, route('words.index', ['status' => 'new']));
        assertChip($response, 'Mastered', 3, route('words.index', ['status' => 'mastered']));
        assertChip($response, 'Troublesome', 1, route('words.index', ['status' => 'troublesome']));
        assertChip($response, 'Learning', 0, route('words.index', ['status' => 'learning']));
    });

    test('narrow the list to one status when tapped', function () {
        /** @var TestCase $this */
        Word::factory()->create(['word' => 'apricity']);
        Word::factory()->troublesome()->create(['word' => 'liminal']);

        $this->get(route('words.index'))->assertSee(route('words.index', ['status' => 'troublesome']));

        $this->get(route('words.index', ['status' => 'troublesome']))
            ->assertSuccessful()
            ->assertSeeText('liminal')
            ->assertDontSeeText('apricity');
    });

    test('are kept by the sort links', function () {
        /** @var TestCase $this */
        $this->get(route('words.index', ['status' => 'mastered']))
            ->assertSee(route('words.index', ['status' => 'mastered', 'sort' => 'a_to_z']));
    });

    test('say so when no word has the chosen status', function () {
        /** @var TestCase $this */
        Word::factory()->create();

        $this->get('/words?status=mastered')->assertSeeText('No mastered words yet.');
    });
});

test('refuses a learning status it does not know', function () {
    /** @var TestCase $this */
    $this->get('/words?status=forgotten')->assertSessionHasErrors('status');
});
