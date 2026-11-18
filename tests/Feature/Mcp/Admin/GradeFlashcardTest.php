<?php

use App\Fsrs\Rating;
use App\Mcp\Servers\AdminServer;
use App\Mcp\Tools\Admin\GradeFlashcard;
use App\Models\Flashcard;
use App\Models\User;
use App\Models\Word;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

describe('shouldRegister()', function () {
    test('an anonymous caller cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $word = Word::factory()->create();

        $response = AdminServer::tool(GradeFlashcard::class, ['flashcard_id' => $word->flashcard->id, 'result' => 'got_it']);

        $response->assertHasErrors(['Tool [grade-flashcard] not found']);
        expect($word->flashcard->reviews()->count())->toBe(0);
    });

    test('a non-admin cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $word = Word::factory()->create();
        $user = User::factory()->create(['is_admin' => false]);

        $response = AdminServer::actingAs($user)
            ->tool(GradeFlashcard::class, ['flashcard_id' => $word->flashcard->id, 'result' => 'got_it']);

        $response->assertHasErrors(['Tool [grade-flashcard] not found']);
        expect($word->flashcard->reviews()->count())->toBe(0);
    });
});

describe('handle()', function () {
    test('refuses a caller who may not practise this flashcard', function () {
        /** @var TestCase $this */
        // Every ability is admin-only today, so the refusal is forced here to
        // prove handle() asks FlashcardPolicy::practice about this flashcard.
        $word = Word::factory()->create();
        Gate::before(function (User $user, string $ability, array $arguments) use ($word): ?bool {
            $isPractisingThisFlashcard = $ability === 'practice'
                && ($arguments[0] ?? null) instanceof Flashcard
                && $arguments[0]->is($word->flashcard);

            return $isPractisingThisFlashcard ? false : null;
        });

        $response = AdminServer::actingAs(User::factory()->admin()->create())
            ->tool(GradeFlashcard::class, ['flashcard_id' => $word->flashcard->id, 'result' => 'got_it']);

        $response->assertHasErrors(['You are not authorized to grade this flashcard.']);
        expect($word->flashcard->reviews()->count())->toBe(0);
    });

    test('records "got it" as a Good review and brings the word back later', function () {
        /** @var TestCase $this */
        $this->freezeTime();
        $word = Word::factory()->create(['word' => 'ephemeral']);

        $response = AdminServer::actingAs(User::factory()->admin()->create())
            ->tool(GradeFlashcard::class, ['flashcard_id' => $word->flashcard->id, 'result' => 'got_it']);

        $flashcard = $word->flashcard->refresh();
        $response->assertOk();
        $response->assertStructuredContent([
            'flashcard_id' => $flashcard->id,
            'word' => 'ephemeral',
            'result' => 'got_it',
            'next_due_at' => $flashcard->schedule->due->format(DATE_ATOM),
        ]);
        expect($flashcard->reviews()->sole()->rating)->toBe(Rating::Good)
            ->and($flashcard->schedule->due > now())->toBeTrue();
    });

    test('records "missed it" as an Again review', function () {
        /** @var TestCase $this */
        $word = Word::factory()->create(['word' => 'ephemeral']);

        $response = AdminServer::actingAs(User::factory()->admin()->create())
            ->tool(GradeFlashcard::class, ['flashcard_id' => $word->flashcard->id, 'result' => 'missed_it']);

        $response->assertOk();
        expect($word->flashcard->reviews()->sole()->rating)->toBe(Rating::Again);
    });

    test('refuses a result other than got_it or missed_it', function () {
        /** @var TestCase $this */
        $word = Word::factory()->create();

        $response = AdminServer::actingAs(User::factory()->admin()->create())
            ->tool(GradeFlashcard::class, ['flashcard_id' => $word->flashcard->id, 'result' => 'easy']);

        $response->assertHasErrors(['The selected result is invalid.']);
        expect($word->flashcard->reviews()->count())->toBe(0);
    });
});
