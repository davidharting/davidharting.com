<?php

use App\Mcp\Servers\AdminServer;
use App\Mcp\Tools\Admin\EditWord;
use App\Models\User;
use App\Models\Word;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Tests\TestCase;

describe('shouldRegister()', function () {
    test('an anonymous caller cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $word = Word::factory()->create(['word' => 'ephemerel']);

        $response = AdminServer::tool(EditWord::class, ['word_id' => $word->id, 'word' => 'ephemeral']);

        $response->assertHasErrors(['Tool [edit-word] not found']);
        expect($word->refresh()->word)->toBe('ephemerel');
    });

    test('a non-admin cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $word = Word::factory()->create(['word' => 'ephemerel']);
        $user = User::factory()->create(['is_admin' => false]);

        $response = AdminServer::actingAs($user)->tool(EditWord::class, ['word_id' => $word->id, 'word' => 'ephemeral']);

        $response->assertHasErrors(['Tool [edit-word] not found']);
        expect($word->refresh()->word)->toBe('ephemerel');
    });
});

describe('handle()', function () {
    test('refuses a non-admin, even if it is reached', function () {
        /** @var TestCase $this */
        // Called directly, since going through the server would only prove
        // shouldRegister works. handle() must refuse on its own too.
        $word = Word::factory()->create(['word' => 'ephemerel']);
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $response = (new EditWord)->handle(new Request(['word_id' => $word->id, 'word' => 'ephemeral']));

        expect($response->isError())->toBeTrue()
            ->and((string) $response->content())->toBe('You are not authorized to edit this word.')
            ->and($word->refresh()->word)->toBe('ephemerel');
    });

    test('asks WordPolicy about this particular word', function () {
        /** @var TestCase $this */
        // Every ability is admin-only today, so the refusal is forced here to
        // prove handle() asks WordPolicy::update about this word.
        $word = Word::factory()->create(['word' => 'ephemerel']);
        Gate::before(fn (User $user, string $ability, array $arguments) => $ability === 'update' && ($arguments[0] ?? null)?->is($word) ? false : null);

        $response = AdminServer::actingAs(User::factory()->admin()->create())
            ->tool(EditWord::class, ['word_id' => $word->id, 'word' => 'ephemeral']);

        $response->assertHasErrors(['You are not authorized to edit this word.']);
        expect($word->refresh()->word)->toBe('ephemerel');
    });

    test('changes only the fields passed', function () {
        /** @var TestCase $this */
        $senses = [['part_of_speech' => 'adjective', 'definition' => 'lasting a very short time', 'examples' => []]];
        $word = Word::factory()->create(['word' => 'ephemerel', 'short_definition' => 'brief', 'senses' => $senses]);

        $response = AdminServer::actingAs(User::factory()->admin()->create())
            ->tool(EditWord::class, ['word_id' => $word->id, 'word' => 'ephemeral']);

        $response->assertOk();
        $response->assertStructuredContent([
            'word_id' => $word->id,
            'word' => 'ephemeral',
            'short_definition' => 'brief',
            'senses' => $senses,
        ]);
    });

    test('replaces the whole list of senses', function () {
        /** @var TestCase $this */
        $word = Word::factory()->create();

        AdminServer::actingAs(User::factory()->admin()->create())->tool(EditWord::class, [
            'word_id' => $word->id,
            'senses' => [['definition' => 'lasting a very short time']],
        ])->assertOk();

        expect($word->refresh()->senses)->toBe([
            ['part_of_speech' => null, 'definition' => 'lasting a very short time', 'examples' => []],
        ]);
    });

    test('clears the short definition when passed null', function () {
        /** @var TestCase $this */
        $word = Word::factory()->create(['short_definition' => 'brief']);

        AdminServer::actingAs(User::factory()->admin()->create())
            ->tool(EditWord::class, ['word_id' => $word->id, 'short_definition' => null])
            ->assertOk();

        expect($word->refresh()->short_definition)->toBeNull();
    });

    test('refuses a call with nothing to change', function () {
        /** @var TestCase $this */
        $word = Word::factory()->create();

        AdminServer::actingAs(User::factory()->admin()->create())
            ->tool(EditWord::class, ['word_id' => $word->id])
            ->assertHasErrors(['Nothing to change: pass at least one field to edit alongside word_id.']);
    });

    test('allows a change of case to its own spelling', function () {
        /** @var TestCase $this */
        $word = Word::factory()->create(['word' => 'ephemeral']);

        AdminServer::actingAs(User::factory()->admin()->create())
            ->tool(EditWord::class, ['word_id' => $word->id, 'word' => 'Ephemeral'])
            ->assertOk();

        expect($word->refresh()->word)->toBe('Ephemeral');
    });

    test('refuses a respelling onto another word on the list, and names it', function () {
        /** @var TestCase $this */
        $existing = Word::factory()->create(['word' => 'ephemeral']);
        $word = Word::factory()->create(['word' => 'ephemerel']);

        AdminServer::actingAs(User::factory()->admin()->create())
            ->tool(EditWord::class, ['word_id' => $word->id, 'word' => 'EPHEMERAL'])
            ->assertHasErrors([sprintf('Refused: "ephemeral" is already on the list as word_id %d. Nothing was changed.', $existing->id)]);

        expect($word->refresh()->word)->toBe('ephemerel');
    });
});
