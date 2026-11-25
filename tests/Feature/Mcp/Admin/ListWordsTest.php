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
 * The words the response lists, in order.
 *
 * @param  array<string, mixed>  $arguments
 * @return list<string>
 */
function listedWords(array $arguments = []): array
{
    $admin = User::factory()->admin()->create();

    $words = [];
    AdminServer::actingAs($admin)->tool(ListWords::class, $arguments)
        ->assertOk()
        ->assertStructuredContent(function ($json) use (&$words) {
            $words = array_column($json->toArray()['words'], 'word');
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

    test('refuses a search and points to search-words', function () {
        /** @var TestCase $this */
        $admin = User::factory()->admin()->create();

        AdminServer::actingAs($admin)->tool(ListWords::class, ['search' => 'canny'])
            ->assertHasErrors(['list-words only browses. Use search-words to find words.']);
    });

    test('rejects the best match sort, which is for search-words', function () {
        /** @var TestCase $this */
        $admin = User::factory()->admin()->create();

        AdminServer::actingAs($admin)->tool(ListWords::class, ['sort' => 'best_match'])->assertHasErrors();
    });

    test('rejects an unknown sort', function () {
        /** @var TestCase $this */
        $admin = User::factory()->admin()->create();

        $response = AdminServer::actingAs($admin)->tool(ListWords::class, ['sort' => 'oldest']);

        $response->assertHasErrors();
    });
});
