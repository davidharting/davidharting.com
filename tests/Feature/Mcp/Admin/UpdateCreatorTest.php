<?php

use App\Mcp\Servers\AdminServer;
use App\Mcp\Tools\Admin\UpdateCreator;
use App\Models\Creator;
use App\Models\Media;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Tests\TestCase;

describe('shouldRegister()', function () {
    test('an anonymous caller cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $creator = Creator::factory()->create(['name' => 'Frank Herbet']);

        $response = AdminServer::tool(UpdateCreator::class, ['creator_id' => $creator->id, 'name' => 'Frank Herbert']);

        $response->assertHasErrors(['Tool [update-creator] not found']);
        expect($creator->refresh()->name)->toBe('Frank Herbet');
    });

    test('a non-admin cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $creator = Creator::factory()->create(['name' => 'Frank Herbet']);
        $user = User::factory()->create(['is_admin' => false]);

        $response = AdminServer::actingAs($user)->tool(UpdateCreator::class, ['creator_id' => $creator->id, 'name' => 'Frank Herbert']);

        $response->assertHasErrors(['Tool [update-creator] not found']);
        expect($creator->refresh()->name)->toBe('Frank Herbet');
    });
});

describe('handle()', function () {
    test('refuses a non-admin, even if it is reached', function () {
        /** @var TestCase $this */
        // Called directly, since going through the server would only prove
        // shouldRegister works. handle() must refuse on its own too.
        $creator = Creator::factory()->create(['name' => 'Frank Herbet']);
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $response = (new UpdateCreator)->handle(new Request(['creator_id' => $creator->id, 'name' => 'Frank Herbert']));

        expect($response->isError())->toBeTrue()
            ->and((string) $response->content())->toBe('You are not authorized to edit this creator.')
            ->and($creator->refresh()->name)->toBe('Frank Herbet');
    });

    test('refuses a caller who may not update this creator', function () {
        /** @var TestCase $this */
        // Every ability is admin-only today, so the refusal is forced here to
        // prove handle() asks CreatorPolicy::update about this creator.
        $creator = Creator::factory()->create(['name' => 'Frank Herbet']);
        Gate::before(fn (User $user, string $ability, array $arguments) => $ability === 'update' && ($arguments[0] ?? null)?->is($creator) ? false : null);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(UpdateCreator::class, ['creator_id' => $creator->id, 'name' => 'Frank Herbert']);

        $response->assertHasErrors(['You are not authorized to edit this creator.']);
        expect($creator->refresh()->name)->toBe('Frank Herbet');
    });

    test('renames the creator on every one of their works', function () {
        /** @var TestCase $this */
        $creator = Creator::factory()->create(['name' => 'Frank Herbet']);
        $dune = Media::factory()->book()->create(['title' => 'Dune', 'creator_id' => $creator->id]);
        $messiah = Media::factory()->book()->create(['title' => 'Dune Messiah', 'creator_id' => $creator->id]);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(UpdateCreator::class, ['creator_id' => $creator->id, 'name' => 'Frank Herbert']);

        $response->assertOk();
        $response->assertStructuredContent([
            'creator_id' => $creator->id,
            'name' => 'Frank Herbert',
            'previous_name' => 'Frank Herbet',
            'changed' => true,
            'media_count' => 2,
        ]);

        expect($creator->refresh()->name)->toBe('Frank Herbert')
            ->and($dune->refresh()->creator->name)->toBe('Frank Herbert')
            ->and($messiah->refresh()->creator->name)->toBe('Frank Herbert')
            ->and(Creator::count())->toBe(1);
    });

    test('allows a change of case to its own name', function () {
        /** @var TestCase $this */
        $creator = Creator::factory()->create(['name' => 'frank herbert']);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(UpdateCreator::class, ['creator_id' => $creator->id, 'name' => 'Frank Herbert']);

        $response->assertOk();
        expect($creator->refresh()->name)->toBe('Frank Herbert');
    });

    test('reports no change when the name matches what is stored', function () {
        /** @var TestCase $this */
        $creator = Creator::factory()->create(['name' => 'Frank Herbert']);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(UpdateCreator::class, ['creator_id' => $creator->id, 'name' => 'Frank Herbert']);

        $response->assertStructuredContent(function ($json) {
            $json->where('changed', false)
                ->where('previous_name', 'Frank Herbert')
                ->where('media_count', 0)
                ->etc();
        });
    });
});

describe('handle() name collisions', function () {
    test('refuses a rename onto another creator\'s name, case-insensitively, and names them', function (string $name) {
        /** @var TestCase $this */
        $existing = Creator::factory()->create(['name' => 'Frank Herbert']);
        $creator = Creator::factory()->create(['name' => 'Frank Herbet']);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(UpdateCreator::class, ['creator_id' => $creator->id, 'name' => $name]);

        $response->assertHasErrors([sprintf('Refused: creator_id %d is already named "Frank Herbert"', $existing->id)]);
        expect($creator->refresh()->name)->toBe('Frank Herbet')
            ->and(Creator::count())->toBe(2);
    })->with([
        'exact' => 'Frank Herbert',
        'different case' => 'FRANK HERBERT',
    ]);

    test('never merges: both creators keep their works', function () {
        /** @var TestCase $this */
        $existing = Creator::factory()->create(['name' => 'Frank Herbert']);
        $creator = Creator::factory()->create(['name' => 'Frank Herbet']);
        $dune = Media::factory()->create(['creator_id' => $existing->id]);
        $messiah = Media::factory()->create(['creator_id' => $creator->id]);
        $admin = User::factory()->create(['is_admin' => true]);

        AdminServer::actingAs($admin)->tool(UpdateCreator::class, ['creator_id' => $creator->id, 'name' => 'frank herbert'])
            ->assertHasErrors(['never merges']);

        expect($dune->refresh()->creator_id)->toBe($existing->id)
            ->and($messiah->refresh()->creator_id)->toBe($creator->id);
    });
});

describe('handle() validation', function () {
    test('rejects a creator id that does not exist', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(UpdateCreator::class, ['creator_id' => 999999, 'name' => 'Frank Herbert']);

        $response->assertHasErrors(['creator id']);
    });

    test('requires a name, since a creator\'s name cannot be cleared', function (array $arguments) {
        /** @var TestCase $this */
        $creator = Creator::factory()->create(['name' => 'Frank Herbet']);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(UpdateCreator::class, ['creator_id' => $creator->id, ...$arguments]);

        $response->assertHasErrors(['name']);
        expect($creator->refresh()->name)->toBe('Frank Herbet');
    })->with([
        'omitted' => [[]],
        'empty' => [['name' => '']],
    ]);

    test('rejects a name longer than 255 characters', function () {
        /** @var TestCase $this */
        $creator = Creator::factory()->create(['name' => 'Frank Herbet']);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(UpdateCreator::class, ['creator_id' => $creator->id, 'name' => str_repeat('a', 256)]);

        $response->assertHasErrors(['name']);
    });
});

describe('annotations', function () {
    test('advertises a destructive, idempotent write', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->withToken(accessTokenFor($admin, ['mcp:use']))
            ->postJson('/mcp/admin', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);

        $tool = collect($response->json('result.tools'))->firstWhere('name', 'update-creator');

        expect($tool['annotations'])->toEqualCanonicalizing([
            'idempotentHint' => true,
            'destructiveHint' => true,
        ]);
    });
});
