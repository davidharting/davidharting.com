<?php

use App\Mcp\Servers\AdminServer;
use App\Mcp\Tools\Admin\RevealFlashcard;
use App\Models\Flashcard;
use App\Models\User;
use App\Models\Word;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Tests\TestCase;

describe('shouldRegister()', function () {
    test('an anonymous caller cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $word = Word::factory()->create();

        $response = AdminServer::tool(RevealFlashcard::class, ['flashcard_id' => $word->flashcard->id]);

        $response->assertHasErrors(['Tool [reveal-flashcard] not found']);
    });

    test('a non-admin cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $word = Word::factory()->create();
        $user = User::factory()->create(['is_admin' => false]);

        $response = AdminServer::actingAs($user)->tool(RevealFlashcard::class, ['flashcard_id' => $word->flashcard->id]);

        $response->assertHasErrors(['Tool [reveal-flashcard] not found']);
    });
});

describe('handle()', function () {
    test('refuses a non-admin, even if it is reached', function () {
        /** @var TestCase $this */
        // Called directly, since going through the server would only prove
        // shouldRegister works. handle() must refuse on its own too.
        $word = Word::factory()->create(['short_definition' => 'lasting a very short time']);
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $response = (new RevealFlashcard)->handle(new Request(['flashcard_id' => $word->flashcard->id]));

        expect($response->isError())->toBeTrue()
            ->and((string) $response->content())->toBe('You are not authorized to see this flashcard.');
    });

    test('refuses a caller who may not view this flashcard', function () {
        /** @var TestCase $this */
        // Every ability is admin-only today, so the refusal is forced here to
        // prove handle() asks FlashcardPolicy::view about this flashcard.
        $word = Word::factory()->create(['short_definition' => 'lasting a very short time']);
        Gate::before(function (User $user, string $ability, array $arguments) use ($word): ?bool {
            $isViewingThisFlashcard = $ability === 'view'
                && ($arguments[0] ?? null) instanceof Flashcard
                && $arguments[0]->is($word->flashcard);

            return $isViewingThisFlashcard ? false : null;
        });

        $response = AdminServer::actingAs(User::factory()->admin()->create())
            ->tool(RevealFlashcard::class, ['flashcard_id' => $word->flashcard->id]);

        $response->assertHasErrors(['You are not authorized to see this flashcard.']);
        $response->assertDontSee('lasting a very short time');
    });

    test('gives only the short definition by default', function () {
        /** @var TestCase $this */
        $word = Word::factory()->create([
            'word' => 'ephemeral',
            'short_definition' => 'lasting a very short time',
            'full_definition' => '**adjective** lasting a very short time',
        ]);

        $response = AdminServer::actingAs(User::factory()->admin()->create())
            ->tool(RevealFlashcard::class, ['flashcard_id' => $word->flashcard->id]);

        $response->assertOk();
        $response->assertStructuredContent([
            'flashcard_id' => $word->flashcard->id,
            'word' => 'ephemeral',
            'short_definition' => 'lasting a very short time',
        ]);
    });

    test('gives the full definition too when asked', function () {
        /** @var TestCase $this */
        $word = Word::factory()->create([
            'word' => 'ephemeral',
            'short_definition' => 'lasting a very short time',
            'full_definition' => '**adjective** lasting a very short time',
        ]);

        $response = AdminServer::actingAs(User::factory()->admin()->create())
            ->tool(RevealFlashcard::class, ['flashcard_id' => $word->flashcard->id, 'include_full_definition' => true]);

        $response->assertOk();
        $response->assertStructuredContent([
            'flashcard_id' => $word->flashcard->id,
            'word' => 'ephemeral',
            'short_definition' => 'lasting a very short time',
            'full_definition' => '**adjective** lasting a very short time',
        ]);
    });

    test('refuses a flashcard that does not exist', function () {
        /** @var TestCase $this */
        $response = AdminServer::actingAs(User::factory()->admin()->create())
            ->tool(RevealFlashcard::class, ['flashcard_id' => 999]);

        $response->assertHasErrors(['There is no word flashcard with flashcard_id 999.']);
    });
});
