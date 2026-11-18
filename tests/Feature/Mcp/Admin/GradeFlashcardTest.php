<?php

use App\Fsrs\Rating;
use App\Mcp\Servers\AdminServer;
use App\Mcp\Tools\Admin\GradeFlashcard;
use App\Models\Flashcard;
use App\Models\Review;
use App\Models\User;
use App\Models\Word;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
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
    test('refuses a non-admin, even if it is reached', function () {
        /** @var TestCase $this */
        // Called directly, since going through the server would only prove
        // shouldRegister works. handle() must refuse on its own too.
        $word = Word::factory()->create();
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $response = (new GradeFlashcard)->handle(new Request(['flashcard_id' => $word->flashcard->id, 'result' => 'got_it']));

        expect($response->isError())->toBeTrue()
            ->and((string) $response->content())->toBe('You are not authorized to grade this flashcard.')
            ->and($word->flashcard->reviews()->count())->toBe(0);
    });

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

    test('records "got it" as a Good review', function () {
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
        expect($flashcard->reviews()->sole()->rating)->toBe(Rating::Good);
    });

    test('records "missed it" as an Again review', function () {
        /** @var TestCase $this */
        $this->freezeTime();
        $word = Word::factory()->create(['word' => 'ephemeral']);

        $response = AdminServer::actingAs(User::factory()->admin()->create())
            ->tool(GradeFlashcard::class, ['flashcard_id' => $word->flashcard->id, 'result' => 'missed_it']);

        $flashcard = $word->flashcard->refresh();
        $response->assertOk();
        $response->assertStructuredContent([
            'flashcard_id' => $flashcard->id,
            'word' => 'ephemeral',
            'result' => 'missed_it',
            'next_due_at' => $flashcard->schedule->due->format(DATE_ATOM),
        ]);
        expect($flashcard->reviews()->sole()->rating)->toBe(Rating::Again);
    });

    test('brings a word David got back later than one he missed', function () {
        /** @var TestCase $this */
        $this->freezeTime();
        $got = Word::factory()->create();
        $missed = Word::factory()->create();
        $admin = User::factory()->admin()->create();

        AdminServer::actingAs($admin)->tool(GradeFlashcard::class, ['flashcard_id' => $got->flashcard->id, 'result' => 'got_it'])->assertOk();
        AdminServer::actingAs($admin)->tool(GradeFlashcard::class, ['flashcard_id' => $missed->flashcard->id, 'result' => 'missed_it'])->assertOk();

        expect($got->flashcard->refresh()->schedule->due)
            ->toBeGreaterThan($missed->flashcard->refresh()->schedule->due);
    });

    test('refuses a flashcard that does not exist', function () {
        /** @var TestCase $this */
        $response = AdminServer::actingAs(User::factory()->admin()->create())
            ->tool(GradeFlashcard::class, ['flashcard_id' => 999, 'result' => 'got_it']);

        $response->assertHasErrors(['There is no word flashcard with flashcard_id 999. Nothing was recorded.']);
        expect(Review::count())->toBe(0);
    });

    test('refuses a flashcard that does not quiz a word', function () {
        /** @var TestCase $this */
        $flashcard = Flashcard::newUnreviewed()->forceFill(['quizzable_type' => 'something_else', 'quizzable_id' => 1]);
        $flashcard->save();

        $response = AdminServer::actingAs(User::factory()->admin()->create())
            ->tool(GradeFlashcard::class, ['flashcard_id' => $flashcard->id, 'result' => 'got_it']);

        $response->assertHasErrors([sprintf('There is no word flashcard with flashcard_id %d. Nothing was recorded.', $flashcard->id)]);
        expect(Review::count())->toBe(0);
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
