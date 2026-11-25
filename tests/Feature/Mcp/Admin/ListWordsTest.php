<?php

use App\Fsrs\Rating;
use App\Mcp\Servers\AdminServer;
use App\Mcp\Tools\Admin\ListWords;
use App\Models\User;
use App\Models\Word;
use Carbon\Carbon;
use Laravel\Mcp\Request;
use Tests\TestCase;

/**
 * The words the response lists, in order. Pass a field to get each word's
 * value of it instead, keyed by word (null where the word has none).
 *
 * @param  array<string, mixed>  $arguments
 * @return list<string>|array<string, mixed>
 */
function listedWords(array $arguments = [], ?string $field = null): array
{
    $admin = User::factory()->admin()->create();

    $words = [];
    AdminServer::actingAs($admin)->tool(ListWords::class, $arguments)
        ->assertOk()
        ->assertStructuredContent(function ($json) use (&$words, $field) {
            $listed = $json->toArray()['words'];
            $words = $field === null
                ? array_column($listed, 'word')
                : array_combine(array_column($listed, 'word'), array_map(fn (array $word): mixed => $word[$field] ?? null, $listed));
            $json->etc();
        });

    return $words;
}

/**
 * Grade the word's flashcard "Missed it" this many times.
 */
function missWord(Word $word, int $times): void
{
    foreach (range(1, $times) as $_) {
        $word->flashcard->grade(Rating::Again);
    }
}

describe('shouldRegister()', function () {
    test('an anonymous caller cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $response = AdminServer::tool(ListWords::class);

        $response->assertHasErrors(['Tool [list-words] not found']);
    });

    test('a non-admin cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $user = User::factory()->create(['is_admin' => false]);

        $response = AdminServer::actingAs($user)->tool(ListWords::class);

        $response->assertHasErrors(['Tool [list-words] not found']);
    });
});

describe('handle()', function () {
    test('refuses a non-admin, even if it is reached', function () {
        /** @var TestCase $this */
        // Called directly, since going through the server would only prove
        // shouldRegister works. handle() must refuse on its own too.
        Word::factory()->create(['word' => 'ephemeral']);
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $response = (new ListWords)->handle(new Request);

        expect($response->isError())->toBeTrue()
            ->and((string) $response->content())->toBe('You are not authorized to read David\'s words.');
    });

    test('returns each word with its id, short definition, date added, learning status and misses, but not its full definition', function () {
        /** @var TestCase $this */
        Carbon::setTestNow('2026-11-01 09:30:00');
        $word = Word::factory()->create([
            'word' => 'ephemeral',
            'short_definition' => 'lasting a very short time',
            'full_definition' => '**adjective** lasting a very short time',
        ]);
        $admin = User::factory()->admin()->create();

        $response = AdminServer::actingAs($admin)->tool(ListWords::class);

        $response->assertOk();
        $response->assertStructuredContent([
            'words' => [[
                'word_id' => $word->id,
                'word' => 'ephemeral',
                'short_definition' => 'lasting a very short time',
                'added_at' => '2026-11-01T09:30:00+00:00',
                'status' => 'new',
                'times_missed' => 0,
            ]],
            'total' => 1,
            'total_pages' => 1,
            'page' => 1,
            'per_page' => 25,
            'has_more_pages' => false,
        ]);
    });

    test('includes the full definition when asked', function () {
        /** @var TestCase $this */
        Word::factory()->create(['word' => 'ephemeral', 'full_definition' => '**adjective** lasting a very short time']);
        Word::factory()->create(['word' => 'liminal', 'full_definition' => null]);
        $admin = User::factory()->admin()->create();

        $response = AdminServer::actingAs($admin)->tool(ListWords::class, ['include_full_definition' => true]);

        $response->assertOk();
        $response->assertStructuredContent(function ($json) {
            $fullDefinitions = array_column($json->toArray()['words'], 'full_definition', 'word');
            expect($fullDefinitions)->toBe([
                'liminal' => null,
                'ephemeral' => '**adjective** lasting a very short time',
            ]);
            $json->etc();
        });
    });

    test('lists the most recently added words first by default', function () {
        /** @var TestCase $this */
        Word::factory()->create(['word' => 'apricity', 'created_at' => Carbon::parse('2026-11-01')]);
        Word::factory()->create(['word' => 'zephyr', 'created_at' => Carbon::parse('2026-11-03')]);
        Word::factory()->create(['word' => 'liminal', 'created_at' => Carbon::parse('2026-11-02')]);

        expect(listedWords())->toBe(['zephyr', 'liminal', 'apricity']);
    });

    test('lists words alphabetically, ignoring case, when sorted A to Z', function () {
        /** @var TestCase $this */
        Word::factory()->create(['word' => 'zephyr']);
        Word::factory()->create(['word' => 'Apricity']);
        Word::factory()->create(['word' => 'liminal']);

        expect(listedWords(['sort' => 'a_to_z']))->toBe(['Apricity', 'liminal', 'zephyr']);
    });

    test('lists the words missed most often first when sorted by most missed, then A to Z', function () {
        /** @var TestCase $this */
        missWord(Word::factory()->create(['word' => 'zephyr']), times: 1);
        missWord(Word::factory()->create(['word' => 'liminal']), times: 3);
        missWord(Word::factory()->create(['word' => 'apricity']), times: 1);
        Word::factory()->create(['word' => 'ephemeral'])->flashcard->grade(Rating::Good);

        expect(listedWords(['sort' => 'most_missed']))->toBe(['liminal', 'apricity', 'zephyr', 'ephemeral']);
    });

    test('reports how many times each word has been missed, not counting the times David got it', function () {
        /** @var TestCase $this */
        $word = Word::factory()->create(['word' => 'liminal']);
        missWord($word, times: 2);
        $word->flashcard->grade(Rating::Good);
        $admin = User::factory()->admin()->create();

        AdminServer::actingAs($admin)->tool(ListWords::class)
            ->assertOk()
            ->assertStructuredContent(fn ($json) => $json->where('words.0.times_missed', 2)->etc());
    });

    test('keeps only words with the requested learning status, and reports each word\'s status', function () {
        /** @var TestCase $this */
        Word::factory()->create(['word' => 'apricity']);
        Word::factory()->mastered()->create(['word' => 'liminal']);
        Word::factory()->troublesome()->create(['word' => 'zephyr']);
        Word::factory()->learning()->create(['word' => 'ephemeral']);
        $admin = User::factory()->admin()->create();

        AdminServer::actingAs($admin)->tool(ListWords::class, ['status' => 'troublesome'])
            ->assertOk()
            ->assertStructuredContent(fn ($json) => $json
                ->where('total', 1)
                ->where('words.0.word', 'zephyr')
                ->where('words.0.status', 'troublesome')
                ->etc());
    });

    test('rejects an unknown status', function () {
        /** @var TestCase $this */
        $admin = User::factory()->admin()->create();

        $response = AdminServer::actingAs($admin)->tool(ListWords::class, ['status' => 'forgotten']);

        $response->assertHasErrors();
    });

    test('finds words whose spelling or either definition contains the search text, ignoring case', function () {
        /** @var TestCase $this */
        Word::factory()->create(['word' => 'Ephemeral', 'short_definition' => 'short-lived', 'full_definition' => null]);
        Word::factory()->create(['word' => 'evanescent', 'short_definition' => 'soon passing out of sight, EPHEMERAL', 'full_definition' => null]);
        Word::factory()->create(['word' => 'transient', 'short_definition' => 'brief', 'full_definition' => 'Synonyms: ephemeral, fleeting']);
        Word::factory()->create(['word' => 'liminal', 'short_definition' => 'at a threshold', 'full_definition' => 'between two states']);

        expect(listedWords(['search' => 'ephemeral', 'sort' => 'a_to_z']))->toBe(['Ephemeral', 'evanescent', 'transient']);
    });

    test('ranks a search by best match by default, and says whether each word matched on its spelling or a definition', function () {
        /** @var TestCase $this */
        Word::factory()->create(['word' => 'astute', 'short_definition' => 'canny; sharp', 'full_definition' => null]);
        Word::factory()->create(['word' => 'uncanny', 'short_definition' => 'eerie', 'full_definition' => null]);
        Word::factory()->create(['word' => 'canny', 'short_definition' => 'shrewd', 'full_definition' => null]);

        expect(listedWords(['search' => 'canny'], 'matched_on'))
            ->toBe(['canny' => 'word', 'uncanny' => 'word', 'astute' => 'definition']);
    });

    test('says nothing about matches when not searching', function () {
        /** @var TestCase $this */
        Word::factory()->create(['word' => 'canny']);

        expect(listedWords([], 'matched_on'))->toBe(['canny' => null]);
    });

    test('lists every word when the search is blank', function () {
        /** @var TestCase $this */
        Word::factory()->create(['word' => 'apricity']);
        Word::factory()->create(['word' => 'zephyr']);

        expect(listedWords(['search' => '   ', 'sort' => 'a_to_z']))->toBe(['apricity', 'zephyr']);
    });

    test('matches percent signs and underscores in the search text literally', function () {
        /** @var TestCase $this */
        Word::factory()->create(['word' => 'per_cent', 'short_definition' => 'a hundredth', 'full_definition' => null]);
        Word::factory()->create(['word' => 'percent', 'short_definition' => 'a hundredth', 'full_definition' => null]);

        expect(listedWords(['search' => 'per_']))->toBe(['per_cent']);
    });

    test('pages through words without repeating or skipping any', function () {
        /** @var TestCase $this */
        // Same timestamp for all, so the order rests on the id tiebreaker.
        Carbon::setTestNow('2026-11-01 09:30:00');
        foreach (['alpha', 'bravo', 'charlie', 'delta', 'echo'] as $word) {
            Word::factory()->create(['word' => $word]);
        }

        $pages = [
            listedWords(['per_page' => 2, 'page' => 1]),
            listedWords(['per_page' => 2, 'page' => 2]),
            listedWords(['per_page' => 2, 'page' => 3]),
        ];

        expect($pages)->toBe([['echo', 'delta'], ['charlie', 'bravo'], ['alpha']]);
    });

    test('reports the total, the page count and whether more pages remain', function () {
        /** @var TestCase $this */
        Word::factory()->count(3)->create();
        $admin = User::factory()->admin()->create();

        $response = AdminServer::actingAs($admin)->tool(ListWords::class, ['per_page' => 2]);

        $response->assertOk();
        $response->assertStructuredContent(function ($json) {
            $json->where('total', 3)
                ->where('total_pages', 2)
                ->where('per_page', 2)
                ->where('has_more_pages', true)
                ->has('words', 2)
                ->etc();
        });
    });

    test('accepts up to 200 words per page and rejects more', function () {
        /** @var TestCase $this */
        $admin = User::factory()->admin()->create();

        AdminServer::actingAs($admin)->tool(ListWords::class, ['per_page' => 200])->assertOk();
        AdminServer::actingAs($admin)->tool(ListWords::class, ['per_page' => 201])->assertHasErrors();
    });

    test('rejects an unknown sort', function () {
        /** @var TestCase $this */
        $admin = User::factory()->admin()->create();

        $response = AdminServer::actingAs($admin)->tool(ListWords::class, ['sort' => 'oldest']);

        $response->assertHasErrors();
    });
});
