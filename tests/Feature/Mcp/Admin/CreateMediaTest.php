<?php

use App\Enum\MediaTypeName;
use App\Mcp\Servers\AdminServer;
use App\Mcp\Tools\Admin\CreateMedia;
use App\Models\Creator;
use App\Models\Media;
use App\Models\MediaType;
use App\Models\User;
use Laravel\Mcp\Request;
use Tests\TestCase;

describe('shouldRegister()', function () {
    test('an anonymous caller cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $response = AdminServer::tool(CreateMedia::class, ['title' => 'Dune', 'media_type' => 'book']);

        $response->assertHasErrors(['Tool [create-media] not found']);
        expect(Media::count())->toBe(0);
    });

    test('a non-admin cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $user = User::factory()->create(['is_admin' => false]);

        $response = AdminServer::actingAs($user)->tool(CreateMedia::class, ['title' => 'Dune', 'media_type' => 'book']);

        $response->assertHasErrors(['Tool [create-media] not found']);
        expect(Media::count())->toBe(0);
    });
});

describe('handle()', function () {
    test('refuses a non-admin, even if it is reached', function () {
        /** @var TestCase $this */
        // Called directly, since going through the server would only prove
        // shouldRegister works. handle() must refuse on its own too.
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $response = (new CreateMedia)->handle(new Request([
            'title' => 'Dune',
            'media_type' => 'book',
            'creator' => 'Frank Herbert',
        ]));

        expect($response->isError())->toBeTrue()
            ->and((string) $response->content())->toBe('You are not authorized to add media.')
            ->and(Media::count())->toBe(0)
            ->and(Creator::count())->toBe(0);
    });

    test('creates the item and its creator', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(CreateMedia::class, [
            'title' => 'Dune',
            'media_type' => 'book',
            'creator' => 'Frank Herbert',
            'year' => 1965,
            'remark' => 'Recommended by Sam',
        ]);

        $response->assertOk();

        $media = Media::sole();
        expect($media->title)->toBe('Dune')
            ->and($media->media_type_id)->toBe(MediaType::where('name', MediaTypeName::Book)->sole()->id)
            ->and($media->creator->name)->toBe('Frank Herbert')
            ->and($media->year)->toBe(1965)
            ->and($media->note)->toBe('Recommended by Sam');

        $response->assertStructuredContent([
            'media_id' => $media->id,
            'title' => 'Dune',
            'media_type' => 'book',
            'creator' => 'Frank Herbert',
            'year' => 1965,
            'remark' => 'Recommended by Sam',
            'media_created' => true,
            'creator_created' => true,
            'ignored_fields' => [],
        ]);
    });

    test('reuses an existing creator, matched case-insensitively', function () {
        /** @var TestCase $this */
        $creator = Creator::factory()->create(['name' => 'Frank Herbert']);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(CreateMedia::class, [
            'title' => 'Dune Messiah',
            'media_type' => 'book',
            'creator' => 'frank herbert',
        ]);

        $response->assertOk();
        $response->assertStructuredContent(function ($json) {
            $json->where('creator', 'Frank Herbert')
                ->where('media_created', true)
                ->where('creator_created', false)
                ->etc();
        });

        expect(Creator::count())->toBe(1)
            ->and(Media::sole()->creator_id)->toBe($creator->id);
    });

    test('finds an existing item when title and creator differ only in case', function () {
        /** @var TestCase $this */
        $media = Media::factory()->book()->create([
            'title' => 'Dune',
            'creator_id' => Creator::factory()->create(['name' => 'Frank Herbert']),
            'year' => 1965,
            'note' => 'A standing remark',
        ]);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(CreateMedia::class, [
            'title' => 'dune',
            'media_type' => 'book',
            'creator' => 'FRANK HERBERT',
        ]);

        $response->assertOk();
        $response->assertStructuredContent([
            'media_id' => $media->id,
            'title' => 'Dune',
            'media_type' => 'book',
            'creator' => 'Frank Herbert',
            'year' => 1965,
            'remark' => 'A standing remark',
            'media_created' => false,
            'creator_created' => false,
            'ignored_fields' => [],
        ]);

        expect(Media::count())->toBe(1)
            ->and(Creator::count())->toBe(1);
    });

    test('names the supplied fields it ignored for an existing item, and changes nothing', function () {
        /** @var TestCase $this */
        $media = Media::factory()->book()->create([
            'title' => 'Dune',
            'creator_id' => Creator::factory()->create(['name' => 'Frank Herbert']),
            'year' => 1965,
            'note' => 'A standing remark',
        ]);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(CreateMedia::class, [
            'title' => 'Dune',
            'media_type' => 'book',
            'creator' => 'Frank Herbert',
            'year' => 2021,
            'remark' => 'A different remark',
        ]);

        $response->assertOk();
        $response->assertStructuredContent(function ($json) {
            $json->where('media_created', false)
                ->where('year', 1965)
                ->where('remark', 'A standing remark')
                ->where('ignored_fields', ['year', 'remark'])
                ->etc();
        });

        expect($media->refresh()->year)->toBe(1965)
            ->and($media->note)->toBe('A standing remark');
    });

    test('does not report a supplied field as ignored when it matches what is stored', function () {
        /** @var TestCase $this */
        Media::factory()->book()->create([
            'title' => 'Dune',
            'creator_id' => Creator::factory()->create(['name' => 'Frank Herbert']),
            'year' => 1965,
        ]);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(CreateMedia::class, [
            'title' => 'Dune',
            'media_type' => 'book',
            'creator' => 'Frank Herbert',
            'year' => 1965,
        ]);

        $response->assertStructuredContent(function ($json) {
            $json->where('media_created', false)
                ->where('ignored_fields', [])
                ->etc();
        });
    });

    test('is idempotent', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);
        $arguments = ['title' => 'Dune', 'media_type' => 'book', 'creator' => 'Frank Herbert'];

        AdminServer::actingAs($admin)->tool(CreateMedia::class, $arguments)->assertOk();
        AdminServer::actingAs($admin)->tool(CreateMedia::class, $arguments)->assertOk();

        expect(Media::count())->toBe(1)
            ->and(Creator::count())->toBe(1);
    });

    test('treats the same title under a different media type as a different item', function () {
        /** @var TestCase $this */
        Media::factory()->book()->create([
            'title' => 'Dune',
            'creator_id' => Creator::factory()->create(['name' => 'Frank Herbert']),
        ]);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(CreateMedia::class, [
            'title' => 'Dune',
            'media_type' => 'movie',
            'creator' => 'Frank Herbert',
        ]);

        $response->assertStructuredContent(function ($json) {
            $json->where('media_created', true)->etc();
        });
        expect(Media::count())->toBe(2);
    });

    test('matches an item recorded with no creator instead of duplicating it', function () {
        /** @var TestCase $this */
        $media = Media::factory()->movie()->create(['title' => 'Casablanca', 'creator_id' => null, 'year' => 1942]);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(CreateMedia::class, [
            'title' => 'casablanca',
            'media_type' => 'movie',
            'creator' => 'Michael Curtiz',
            'year' => 1942,
        ]);

        $response->assertOk();
        $response->assertStructuredContent(function ($json) use ($media) {
            $json->where('media_id', $media->id)
                ->where('creator', null)
                ->where('media_created', false)
                ->where('creator_created', false)
                ->where('ignored_fields', ['creator'])
                ->etc();
        });

        // Nothing written: no second item, the creator is not filled in, and
        // no creator is left behind with no works.
        expect(Media::count())->toBe(1)
            ->and($media->refresh()->creator_id)->toBeNull()
            ->and(Creator::count())->toBe(0);
    });

    test('prefers the item with the named creator over one recorded with no creator', function () {
        /** @var TestCase $this */
        Media::factory()->book()->create(['title' => 'Beowulf', 'creator_id' => null]);
        $withCreator = Media::factory()->book()->create([
            'title' => 'Beowulf',
            'creator_id' => Creator::factory()->create(['name' => 'Seamus Heaney']),
        ]);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(CreateMedia::class, [
            'title' => 'Beowulf',
            'media_type' => 'book',
            'creator' => 'Seamus Heaney',
        ]);

        $response->assertStructuredContent(function ($json) use ($withCreator) {
            $json->where('media_id', $withCreator->id)
                ->where('creator', 'Seamus Heaney')
                ->where('ignored_fields', [])
                ->etc();
        });
    });

    test('requires a creator', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(CreateMedia::class, [
            'title' => 'Dune',
            'media_type' => 'book',
        ]);

        $response->assertHasErrors(['creator']);
        expect(Media::count())->toBe(0);
    });

    test('requires a title', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(CreateMedia::class, ['media_type' => 'book', 'creator' => 'Frank Herbert']);

        $response->assertHasErrors(['title']);
        expect(Media::count())->toBe(0);
    });

    test('rejects an unknown media type', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(CreateMedia::class, [
            'title' => 'Dune',
            'media_type' => 'podcast',
            'creator' => 'Frank Herbert',
        ]);

        $response->assertHasErrors(['media type']);
        expect(Media::count())->toBe(0);
    });

    test('rejects an empty creator', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(CreateMedia::class, [
            'title' => 'Dune',
            'media_type' => 'book',
            'creator' => '',
        ]);

        $response->assertHasErrors(['creator']);
        expect(Media::count())->toBe(0);
    });

    test('rejects a year that is not an integer', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(CreateMedia::class, [
            'title' => 'Dune',
            'media_type' => 'book',
            'creator' => 'Frank Herbert',
            'year' => 'nineteen sixty-five',
        ]);

        $response->assertHasErrors(['year']);
        expect(Media::count())->toBe(0);
    });
});

describe('annotations', function () {
    test('advertises an idempotent, non-destructive write', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->withToken(accessTokenFor($admin, ['mcp:use']))
            ->postJson('/mcp/admin', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);

        $tool = collect($response->json('result.tools'))->firstWhere('name', 'create-media');

        // readOnlyHint is omitted rather than false: laravel/mcp only emits
        // the annotations a tool declares.
        expect($tool['annotations'])->toBe([
            'idempotentHint' => true,
            'destructiveHint' => false,
        ]);
    });
});
