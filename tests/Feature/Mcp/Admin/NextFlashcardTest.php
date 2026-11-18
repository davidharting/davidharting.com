<?php

use App\Fsrs\Rating;
use App\Mcp\Servers\AdminServer;
use App\Mcp\Tools\Admin\NextFlashcard;
use App\Models\User;
use App\Models\Word;
use Laravel\Mcp\Request;
use Tests\TestCase;

/**
 * Record a word whose flashcard is not due yet: a "Got it" on a brand new
 * flashcard brings it back in ten minutes.
 */
function wordNotDueYet(string $word): Word
{
    $recorded = Word::factory()->create(['word' => $word]);
    $recorded->flashcard->grade(Rating::Good);

    return $recorded;
}

describe('shouldRegister()', function () {
    test('an anonymous caller cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $response = AdminServer::tool(NextFlashcard::class);

        $response->assertHasErrors(['Tool [next-flashcard] not found']);
    });

    test('a non-admin cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $user = User::factory()->create(['is_admin' => false]);

        $response = AdminServer::actingAs($user)->tool(NextFlashcard::class);

        $response->assertHasErrors(['Tool [next-flashcard] not found']);
    });
});

describe('handle()', function () {
    test('refuses a non-admin, even if it is reached', function () {
        /** @var TestCase $this */
        // Called directly, since going through the server would only prove
        // shouldRegister works. handle() must refuse on its own too.
        Word::factory()->create(['word' => 'ephemeral']);
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $response = (new NextFlashcard)->handle(new Request);

        expect($response->isError())->toBeTrue()
            ->and((string) $response->content())->toBe('You are not authorized to practise David\'s flashcards.');
    });

    test('gives the most overdue word, without its definition', function () {
        /** @var TestCase $this */
        $this->freezeTime();
        $overdue = Word::factory()->create(['word' => 'ephemeral', 'short_definition' => 'lasting a very short time']);
        $this->travel(1)->hours();
        Word::factory()->create(['word' => 'laconic']);
        wordNotDueYet('sesquipedalian');

        $response = AdminServer::actingAs(User::factory()->admin()->create())->tool(NextFlashcard::class);

        $response->assertOk();
        $response->assertStructuredContent([
            'flashcard' => [
                'flashcard_id' => $overdue->flashcard->id,
                'word' => 'ephemeral',
                'is_due' => true,
            ],
            'due_count' => 2,
            'next_due_at' => $overdue->created_at->format(DATE_ATOM),
        ]);
        $response->assertDontSee('lasting a very short time');
    });

    test('gives no flashcard when none is due, and says when the next one is', function () {
        /** @var TestCase $this */
        $this->freezeTime();
        $word = wordNotDueYet('ephemeral');

        $response = AdminServer::actingAs(User::factory()->admin()->create())->tool(NextFlashcard::class);

        $response->assertOk();
        $response->assertStructuredContent([
            'flashcard' => null,
            'due_count' => 0,
            'next_due_at' => $word->flashcard->schedule->due->format(DATE_ATOM),
        ]);
    });

    test('gives a flashcard that is not due yet when asked to practise ahead', function () {
        /** @var TestCase $this */
        $word = wordNotDueYet('ephemeral');

        $response = AdminServer::actingAs(User::factory()->admin()->create())
            ->tool(NextFlashcard::class, ['include_not_due' => true]);

        $response->assertOk();
        $response->assertStructuredContent([
            'flashcard' => [
                'flashcard_id' => $word->flashcard->id,
                'word' => 'ephemeral',
                'is_due' => false,
            ],
            'due_count' => 0,
            'next_due_at' => $word->flashcard->schedule->due->format(DATE_ATOM),
        ]);
    });

    test('gives nothing at all when there are no words', function () {
        /** @var TestCase $this */
        $response = AdminServer::actingAs(User::factory()->admin()->create())->tool(NextFlashcard::class);

        $response->assertOk();
        $response->assertStructuredContent([
            'flashcard' => null,
            'due_count' => 0,
            'next_due_at' => null,
        ]);
    });
});
