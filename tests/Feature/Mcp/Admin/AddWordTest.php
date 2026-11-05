<?php

use App\Mcp\Servers\AdminServer;
use App\Mcp\Tools\Admin\AddWord;
use App\Models\User;
use App\Models\Word;
use Laravel\Mcp\Request;
use Tests\TestCase;

describe('shouldRegister()', function () {
    test('an anonymous caller cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $response = AdminServer::tool(AddWord::class, ['word' => 'ephemeral']);

        $response->assertHasErrors(['Tool [add-word] not found']);
        expect(Word::count())->toBe(0);
    });

    test('a non-admin cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $user = User::factory()->create(['is_admin' => false]);

        $response = AdminServer::actingAs($user)->tool(AddWord::class, ['word' => 'ephemeral']);

        $response->assertHasErrors(['Tool [add-word] not found']);
        expect(Word::count())->toBe(0);
    });
});

describe('handle()', function () {
    test('refuses a non-admin, even if it is reached', function () {
        /** @var TestCase $this */
        // Called directly, since going through the server would only prove
        // shouldRegister works. handle() must refuse on its own too.
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $response = (new AddWord)->handle(new Request(['word' => 'ephemeral']));

        expect($response->isError())->toBeTrue()
            ->and((string) $response->content())->toBe('You are not authorized to add words.')
            ->and(Word::count())->toBe(0);
    });

    test('adds a word with its senses and short definition', function () {
        /** @var TestCase $this */
        $admin = User::factory()->admin()->create();

        $response = AdminServer::actingAs($admin)->tool(AddWord::class, [
            'word' => 'ephemeral',
            'short_definition' => 'here today, gone tomorrow',
            'senses' => [
                ['part_of_speech' => 'adjective', 'definition' => 'lasting a very short time', 'examples' => ['an ephemeral fashion']],
            ],
        ]);

        $word = Word::sole();
        $response->assertOk();
        $response->assertStructuredContent([
            'word_id' => $word->id,
            'word' => 'ephemeral',
            'short_definition' => 'here today, gone tomorrow',
            'senses' => [
                ['part_of_speech' => 'adjective', 'definition' => 'lasting a very short time', 'examples' => ['an ephemeral fashion']],
            ],
            'word_created' => true,
        ]);
    });

    test('adds a word on its own, to fill in later', function () {
        /** @var TestCase $this */
        $admin = User::factory()->admin()->create();

        $response = AdminServer::actingAs($admin)->tool(AddWord::class, ['word' => 'ephemeral']);

        $response->assertOk();
        expect(Word::sole())
            ->word->toBe('ephemeral')
            ->senses->toBe([])
            ->short_definition->toBeNull();
    });

    test('returns a word already on the list unchanged, matching case-insensitively', function () {
        /** @var TestCase $this */
        $existing = Word::factory()->create(['word' => 'ephemeral', 'short_definition' => 'brief']);
        $admin = User::factory()->admin()->create();

        $response = AdminServer::actingAs($admin)->tool(AddWord::class, [
            'word' => 'Ephemeral',
            'short_definition' => 'here today, gone tomorrow',
        ]);

        $response->assertStructuredContent(function ($json) use ($existing) {
            $json->where('word_id', $existing->id)
                ->where('word', 'ephemeral')
                ->where('short_definition', 'brief')
                ->where('word_created', false)
                ->etc();
        });
        expect(Word::count())->toBe(1)
            ->and($existing->refresh()->short_definition)->toBe('brief');
    });

    test('ignores whitespace around the word, so it still finds the stored one', function () {
        /** @var TestCase $this */
        $existing = Word::factory()->create(['word' => 'ephemeral']);

        $response = AdminServer::actingAs(User::factory()->admin()->create())
            ->tool(AddWord::class, ['word' => " Ephemeral\n"]);

        $response->assertStructuredContent(fn ($json) => $json->where('word_id', $existing->id)->where('word_created', false)->etc());
        expect(Word::count())->toBe(1);
    });

    test('refuses a sense with no definition', function () {
        /** @var TestCase $this */
        $admin = User::factory()->admin()->create();

        $response = AdminServer::actingAs($admin)->tool(AddWord::class, [
            'word' => 'ephemeral',
            'senses' => [['part_of_speech' => 'adjective']],
        ]);

        $response->assertHasErrors(['The senses.0.definition field is required.']);
        expect(Word::count())->toBe(0);
    });
});
