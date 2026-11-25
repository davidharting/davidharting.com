<?php

use App\Mcp\Servers\AdminServer;
use App\Mcp\Tools\Admin\SearchWords;
use App\Models\User;
use App\Models\Word;
use Laravel\Mcp\Request;
use Tests\TestCase;

/**
 * What search-words matched each word it found on, keyed by word, in order.
 *
 * @param  array<string, mixed>  $arguments
 * @return array<string, string>
 */
function searchedWords(array $arguments): array
{
    $admin = User::factory()->admin()->create();

    $matches = [];
    AdminServer::actingAs($admin)->tool(SearchWords::class, $arguments)
        ->assertOk()
        ->assertStructuredContent(function ($json) use (&$matches) {
            $words = $json->toArray()['words'];
            $matches = array_combine(array_column($words, 'word'), array_column($words, 'matched_on'));
            $json->etc();
        });

    return $matches;
}

describe('shouldRegister()', function () {
    test('an anonymous caller cannot reach the tool at all', function () {
        /** @var TestCase $this */
        AdminServer::tool(SearchWords::class, ['query' => 'canny'])
            ->assertHasErrors(['Tool [search-words] not found']);
    });

    test('a non-admin cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $user = User::factory()->create(['is_admin' => false]);

        AdminServer::actingAs($user)->tool(SearchWords::class, ['query' => 'canny'])
            ->assertHasErrors(['Tool [search-words] not found']);
    });
});

describe('handle()', function () {
    test('refuses a non-admin, even if it is reached', function () {
        /** @var TestCase $this */
        // Called directly, since going through the server would only prove
        // shouldRegister works. handle() must refuse on its own too.
        Word::factory()->create(['word' => 'canny']);
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $response = (new SearchWords)->handle(new Request(['query' => 'canny']));

        expect($response->isError())->toBeTrue();
    });

    test('ranks the word itself first, then words starting with the query, then words containing it, then definition matches', function () {
        /** @var TestCase $this */
        Word::factory()->create(['word' => 'astute', 'short_definition' => 'canny; sharp', 'full_definition' => null]);
        Word::factory()->create(['word' => 'uncanny', 'short_definition' => 'eerie', 'full_definition' => null]);
        Word::factory()->create(['word' => 'cannily', 'short_definition' => 'shrewdly', 'full_definition' => null]);
        Word::factory()->create(['word' => 'cann', 'short_definition' => 'to steer a ship', 'full_definition' => null]);

        expect(searchedWords(['query' => 'cann']))
            ->toBe(['cann' => 'word', 'cannily' => 'word', 'uncanny' => 'word', 'astute' => 'definition']);
    });

    test('finds words whose spelling or either definition contains the query, ignoring case', function () {
        /** @var TestCase $this */
        Word::factory()->create(['word' => 'Ephemeral', 'short_definition' => 'short-lived', 'full_definition' => null]);
        Word::factory()->create(['word' => 'evanescent', 'short_definition' => 'soon passing out of sight, EPHEMERAL', 'full_definition' => null]);
        Word::factory()->create(['word' => 'transient', 'short_definition' => 'brief', 'full_definition' => 'Synonyms: ephemeral, fleeting']);
        Word::factory()->create(['word' => 'liminal', 'short_definition' => 'at a threshold', 'full_definition' => 'between two states']);

        expect(array_keys(searchedWords(['query' => 'ephemeral'])))->toBe(['Ephemeral', 'evanescent', 'transient']);
    });

    test('matches percent signs and underscores in the query literally', function () {
        /** @var TestCase $this */
        Word::factory()->create(['word' => 'per_cent', 'short_definition' => 'a hundredth', 'full_definition' => null]);
        Word::factory()->create(['word' => 'percent', 'short_definition' => 'a hundredth', 'full_definition' => null]);

        expect(array_keys(searchedWords(['query' => 'per_'])))->toBe(['per_cent']);
    });

    test('requires a query that is not blank', function (array $arguments) {
        /** @var TestCase $this */
        $admin = User::factory()->admin()->create();

        AdminServer::actingAs($admin)->tool(SearchWords::class, $arguments)->assertHasErrors();
    })->with(['no query' => [[]], 'a blank query' => [['query' => '   ']]]);
});
