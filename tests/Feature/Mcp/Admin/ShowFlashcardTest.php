<?php

use App\Fsrs\Rating;
use App\Mcp\Resources\FlashcardApp;
use App\Mcp\Servers\AdminServer;
use App\Mcp\Tools\Admin\ShowFlashcard;
use App\Models\User;
use App\Models\Word;
use Laravel\Mcp\Request;
use Tests\TestCase;

describe('shouldRegister()', function () {
    test('an anonymous caller cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $response = AdminServer::tool(ShowFlashcard::class);

        $response->assertHasErrors(['Tool [show-flashcard] not found']);
    });

    test('a non-admin cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $user = User::factory()->create(['is_admin' => false]);

        $response = AdminServer::actingAs($user)->tool(ShowFlashcard::class);

        $response->assertHasErrors(['Tool [show-flashcard] not found']);
    });
});

describe('toArray()', function () {
    test('points the host at the flashcard app to render', function () {
        /** @var TestCase $this */
        $listing = (new ShowFlashcard)->toArray();

        expect($listing['_meta']['ui'])->toBe([
            'resourceUri' => (new FlashcardApp)->uri(),
            'visibility' => ['model', 'app'],
        ]);
    });
});

describe('handle()', function () {
    test('refuses a non-admin, even if it is reached', function () {
        /** @var TestCase $this */
        // Called directly, since going through the server would only prove
        // shouldRegister works. handle() must refuse on its own too.
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $response = (new ShowFlashcard)->handle(new Request);

        expect($response->isError())->toBeTrue()
            ->and((string) $response->content())->toBe('You are not authorized to practise David\'s flashcards.');
    });

    test('tells the model how many words are due, and no word or definition', function () {
        /** @var TestCase $this */
        Word::factory()->create(['word' => 'ephemeral', 'short_definition' => 'lasting a very short time']);
        Word::factory()->create(['word' => 'laconic']);

        $response = AdminServer::actingAs(User::factory()->admin()->create())->tool(ShowFlashcard::class);

        $response->assertOk();
        $response->assertSee('Showing David a flashcard to flip and grade himself. 2 words are due now.');
        $response->assertDontSee('ephemeral');
        $response->assertDontSee('lasting a very short time');
    });

    test('counts only words that are due, in the singular for one', function () {
        /** @var TestCase $this */
        Word::factory()->create(['word' => 'ephemeral']);
        // A "Got it" on a new word brings it back in ten minutes, so it is not due.
        Word::factory()->create(['word' => 'laconic'])->flashcard->grade(Rating::Good);

        $response = AdminServer::actingAs(User::factory()->admin()->create())->tool(ShowFlashcard::class);

        $response->assertOk();
        $response->assertSee('Showing David a flashcard to flip and grade himself. 1 word is due now.');
    });
});
