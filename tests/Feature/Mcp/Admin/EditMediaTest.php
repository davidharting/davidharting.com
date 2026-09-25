<?php

use App\Enum\MediaTypeName;
use App\Mcp\Servers\AdminServer;
use App\Mcp\Tools\Admin\EditMedia;
use App\Models\Creator;
use App\Models\Media;
use App\Models\MediaType;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Tests\TestCase;

/**
 * A book with every editable field set, so a test can prove the fields it
 * does not pass are left alone.
 */
function duneBook(): Media
{
    return Media::factory()->book()->create([
        'title' => 'Dune',
        'creator_id' => Creator::factory()->create(['name' => 'Frank Herbert']),
        'year' => 1965,
        'note' => 'A standing remark',
    ]);
}

describe('shouldRegister()', function () {
    test('an anonymous caller cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $media = duneBook();

        $response = AdminServer::tool(EditMedia::class, ['media_id' => $media->id, 'title' => 'Dune Messiah']);

        $response->assertHasErrors(['Tool [edit-media] not found']);
        expect($media->refresh()->title)->toBe('Dune');
    });

    test('a non-admin cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $media = duneBook();
        $user = User::factory()->create(['is_admin' => false]);

        $response = AdminServer::actingAs($user)->tool(EditMedia::class, ['media_id' => $media->id, 'title' => 'Dune Messiah']);

        $response->assertHasErrors(['Tool [edit-media] not found']);
        expect($media->refresh()->title)->toBe('Dune');
    });
});

describe('handle()', function () {
    test('refuses a non-admin, even if it is reached', function () {
        /** @var TestCase $this */
        // Called directly, since going through the server would only prove
        // shouldRegister works. handle() must refuse on its own too.
        $media = duneBook();
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $response = (new EditMedia)->handle(new Request(['media_id' => $media->id, 'title' => 'Dune Messiah']));

        expect($response->isError())->toBeTrue()
            ->and($media->refresh()->title)->toBe('Dune');
    });

    test('refuses a caller who may not update this item', function () {
        /** @var TestCase $this */
        // Every ability is admin-only today, so the refusal is forced here to
        // prove handle() asks MediaPolicy::update about this item.
        $media = duneBook();
        Gate::before(fn (User $user, string $ability) => $ability === 'update' ? false : null);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMedia::class, ['media_id' => $media->id, 'title' => 'Dune Messiah']);

        $response->assertHasErrors(['You are not authorized to edit this item.']);
        expect($media->refresh()->title)->toBe('Dune');
    });

    test('refuses a caller who may not create creators, even when the edit names no creator', function () {
        /** @var TestCase $this */
        // Every ability the tool can exercise is checked whatever the call
        // asks for, so a year-only edit is refused too.
        $media = duneBook();
        Gate::before(fn (User $user, string $ability, array $arguments) => $ability === 'create' && ($arguments[0] ?? null) === Creator::class ? false : null);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMedia::class, ['media_id' => $media->id, 'year' => 1966]);

        $response->assertHasErrors(['You are not authorized to add creators.']);
        expect($media->refresh()->year)->toBe(1965);
    });

    test('refuses a caller who may not read remarks, even when the edit leaves the remark alone', function () {
        /** @var TestCase $this */
        $media = duneBook();
        Gate::before(fn (User $user, string $ability) => $ability === 'seeNote' ? false : null);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMedia::class, ['media_id' => $media->id, 'year' => 1966]);

        $response->assertHasErrors(['You are not authorized to read David\'s remarks.']);
        expect($media->refresh()->year)->toBe(1965);
    });

    test('changes only the fields it is given', function () {
        /** @var TestCase $this */
        $media = duneBook();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMedia::class, ['media_id' => $media->id, 'year' => 1966]);

        $response->assertOk();
        $response->assertStructuredContent([
            'media_id' => $media->id,
            'title' => 'Dune',
            'media_type' => 'book',
            'creator' => 'Frank Herbert',
            'year' => 1966,
            'remark' => 'A standing remark',
            'creator_created' => false,
            'changed_fields' => ['year'],
        ]);

        $media->refresh();
        expect($media->year)->toBe(1966)
            ->and($media->title)->toBe('Dune')
            ->and($media->note)->toBe('A standing remark')
            ->and($media->creator->name)->toBe('Frank Herbert');
    });

    test('corrects the title and media type', function () {
        /** @var TestCase $this */
        $media = duneBook();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMedia::class, [
            'media_id' => $media->id,
            'title' => 'Dune: Part One',
            'media_type' => 'movie',
        ]);

        $response->assertStructuredContent(function ($json) {
            $json->where('title', 'Dune: Part One')
                ->where('media_type', 'movie')
                ->where('changed_fields', ['title', 'media_type'])
                ->etc();
        });

        expect($media->refresh()->title)->toBe('Dune: Part One')
            ->and($media->media_type_id)->toBe(MediaType::where('name', MediaTypeName::Movie)->sole()->id);
    });

    test('allows a change of case to its own title', function () {
        /** @var TestCase $this */
        $media = Media::factory()->book()->create([
            'title' => 'dune',
            'creator_id' => Creator::factory()->create(['name' => 'Frank Herbert']),
        ]);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMedia::class, ['media_id' => $media->id, 'title' => 'Dune']);

        $response->assertOk();
        expect($media->refresh()->title)->toBe('Dune');
    });

    test('clears the year with null', function () {
        /** @var TestCase $this */
        $media = duneBook();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMedia::class, ['media_id' => $media->id, 'year' => null]);

        $response->assertOk();
        expect($media->refresh()->year)->toBeNull()
            ->and($media->note)->toBe('A standing remark');
    });

    test('reports no changed fields when every value matches what is stored', function () {
        /** @var TestCase $this */
        $media = duneBook();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMedia::class, ['media_id' => $media->id, 'year' => 1965]);

        $response->assertStructuredContent(function ($json) {
            $json->where('changed_fields', [])->etc();
        });
    });

    test('refuses a call that names no field to change', function () {
        /** @var TestCase $this */
        $media = duneBook();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMedia::class, ['media_id' => $media->id]);

        $response->assertHasErrors(['Nothing to change']);
    });
});

describe('handle() remark', function () {
    test('replaces the remark', function () {
        /** @var TestCase $this */
        $media = duneBook();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMedia::class, ['media_id' => $media->id, 'replace_remark' => 'A new remark']);

        $response->assertStructuredContent(function ($json) {
            $json->where('remark', 'A new remark')
                ->where('changed_fields', ['remark'])
                ->etc();
        });
        expect($media->refresh()->note)->toBe('A new remark');
    });

    test('clears the remark with an empty replace', function () {
        /** @var TestCase $this */
        $media = duneBook();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMedia::class, ['media_id' => $media->id, 'replace_remark' => '']);

        $response->assertOk();
        $response->assertStructuredContent(function ($json) {
            $json->where('remark', null)->etc();
        });
        expect($media->refresh()->note)->toBeNull()
            ->and($media->year)->toBe(1965);
    });

    test('appends to the remark on a new line and returns the whole of it', function () {
        /** @var TestCase $this */
        $media = duneBook();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMedia::class, ['media_id' => $media->id, 'append_to_remark' => 'Sam lent me his copy']);

        $response->assertStructuredContent(function ($json) {
            $json->where('remark', "A standing remark\nSam lent me his copy")->etc();
        });
        expect($media->refresh()->note)->toBe("A standing remark\nSam lent me his copy");
    });

    test('appending to an empty remark sets it', function () {
        /** @var TestCase $this */
        $media = Media::factory()->book()->create(['note' => null]);
        $admin = User::factory()->create(['is_admin' => true]);

        AdminServer::actingAs($admin)->tool(EditMedia::class, ['media_id' => $media->id, 'append_to_remark' => 'First thought'])->assertOk();

        expect($media->refresh()->note)->toBe('First thought');
    });

    test('rejects an empty append', function () {
        /** @var TestCase $this */
        $media = duneBook();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMedia::class, ['media_id' => $media->id, 'append_to_remark' => '']);

        $response->assertHasErrors(['pass replace_remark as an empty string']);
        expect($media->refresh()->note)->toBe('A standing remark');
    });

    test('rejects replacing and appending at once', function () {
        /** @var TestCase $this */
        $media = duneBook();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMedia::class, [
            'media_id' => $media->id,
            'replace_remark' => 'Replaced',
            'append_to_remark' => 'Appended',
        ]);

        $response->assertHasErrors(['replace remark']);
        expect($media->refresh()->note)->toBe('A standing remark');
    });
});

describe('handle() creator', function () {
    test('re-points the item to an existing creator, matched case-insensitively', function () {
        /** @var TestCase $this */
        $media = duneBook();
        $brian = Creator::factory()->create(['name' => 'Brian Herbert']);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMedia::class, ['media_id' => $media->id, 'creator' => 'brian herbert']);

        $response->assertStructuredContent(function ($json) {
            $json->where('creator', 'Brian Herbert')
                ->where('creator_created', false)
                ->where('changed_fields', ['creator'])
                ->etc();
        });
        expect($media->refresh()->creator_id)->toBe($brian->id)
            ->and(Creator::count())->toBe(2);
    });

    test('re-points the item to an existing creator by id', function () {
        /** @var TestCase $this */
        $media = duneBook();
        $brian = Creator::factory()->create(['name' => 'Brian Herbert']);
        $admin = User::factory()->create(['is_admin' => true]);

        AdminServer::actingAs($admin)->tool(EditMedia::class, ['media_id' => $media->id, 'creator_id' => $brian->id])->assertOk();

        expect($media->refresh()->creator_id)->toBe($brian->id);
    });

    test('creates a creator it does not find, and leaves the old one for its other works', function () {
        /** @var TestCase $this */
        $media = duneBook();
        $frank = $media->creator;
        $messiah = Media::factory()->book()->create(['title' => 'Dune Messiah', 'creator_id' => $frank->id]);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMedia::class, ['media_id' => $media->id, 'creator' => 'Frank Patrick Herbert']);

        $response->assertStructuredContent(function ($json) {
            $json->where('creator', 'Frank Patrick Herbert')
                ->where('creator_created', true)
                ->where('changed_fields', ['creator'])
                ->etc();
        });
        expect($media->refresh()->creator->name)->toBe('Frank Patrick Herbert')
            ->and($messiah->refresh()->creator_id)->toBe($frank->id)
            ->and($frank->refresh()->name)->toBe('Frank Herbert');
    });

    test('fills in the creator on an item recorded with no creator', function () {
        /** @var TestCase $this */
        $media = Media::factory()->movie()->create(['title' => 'Casablanca', 'creator_id' => null]);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMedia::class, ['media_id' => $media->id, 'creator' => 'Michael Curtiz']);

        $response->assertOk();
        expect($media->refresh()->creator->name)->toBe('Michael Curtiz');
    });

    test('rejects an empty creator, since a creator cannot be cleared', function () {
        /** @var TestCase $this */
        $media = duneBook();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMedia::class, ['media_id' => $media->id, 'creator' => '']);

        $response->assertHasErrors(['creator']);
        expect($media->refresh()->creator->name)->toBe('Frank Herbert');
    });

    test('rejects both a creator and a creator id', function () {
        /** @var TestCase $this */
        $media = duneBook();
        $brian = Creator::factory()->create(['name' => 'Brian Herbert']);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMedia::class, [
            'media_id' => $media->id,
            'creator' => 'Kevin J. Anderson',
            'creator_id' => $brian->id,
        ]);

        $response->assertHasErrors(['creator']);
        expect($media->refresh()->creator->name)->toBe('Frank Herbert')
            ->and(Creator::count())->toBe(2);
    });
});

describe('handle() identity collisions', function () {
    test('refuses a title that would duplicate another item, case-insensitively, and names it', function () {
        /** @var TestCase $this */
        $media = duneBook();
        $messiah = Media::factory()->book()->create(['title' => 'Dune Messiah', 'creator_id' => $media->creator_id]);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMedia::class, [
            'media_id' => $media->id,
            'title' => 'DUNE MESSIAH',
            'year' => 1969,
        ]);

        $response->assertHasErrors(["media_id {$messiah->id}, \"Dune Messiah\" by Frank Herbert"]);

        // Nothing is written, not even the fields that did not collide.
        expect($media->refresh()->title)->toBe('Dune')
            ->and($media->year)->toBe(1965);
    });

    test('refuses a media type that would duplicate another item', function () {
        /** @var TestCase $this */
        $media = duneBook();
        $movie = Media::factory()->movie()->create(['title' => 'Dune', 'creator_id' => $media->creator_id]);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMedia::class, ['media_id' => $media->id, 'media_type' => 'movie']);

        $response->assertHasErrors(["media_id {$movie->id}"]);
        expect($media->refresh()->media_type_id)->toBe(MediaType::where('name', MediaTypeName::Book)->sole()->id);
    });

    test('refuses filling in a creator when that item already exists with it', function () {
        /** @var TestCase $this */
        $curtiz = Creator::factory()->create(['name' => 'Michael Curtiz']);
        $withCreator = Media::factory()->movie()->create(['title' => 'Casablanca', 'creator_id' => $curtiz->id]);
        $legacy = Media::factory()->movie()->create(['title' => 'Casablanca', 'creator_id' => null]);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMedia::class, ['media_id' => $legacy->id, 'creator' => 'michael curtiz']);

        $response->assertHasErrors(["media_id {$withCreator->id}"]);
        expect($legacy->refresh()->creator_id)->toBeNull();
    });

    test('refuses a rename onto an item recorded with no creator, and says how to resolve it', function () {
        /** @var TestCase $this */
        $legacy = Media::factory()->movie()->create(['title' => 'Casablanca', 'creator_id' => null]);
        $media = Media::factory()->movie()->create([
            'title' => 'Casablnca',
            'creator_id' => Creator::factory()->create(['name' => 'Michael Curtiz']),
        ]);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMedia::class, ['media_id' => $media->id, 'title' => 'casablanca']);

        $response->assertHasErrors([
            "media_id {$legacy->id}, \"Casablanca\", has the same title and media type and was recorded with no creator",
            "give media_id {$legacy->id} its creator first",
        ]);
        expect($media->refresh()->title)->toBe('Casablnca');
    });

    test('refuses a new creator when an item recorded with no creator has the same title, and creates no creator', function () {
        /** @var TestCase $this */
        $legacy = Media::factory()->movie()->create(['title' => 'Casablanca', 'creator_id' => null]);
        $media = Media::factory()->movie()->create([
            'title' => 'Casablanca',
            'creator_id' => Creator::factory()->create(['name' => 'Someone Else']),
        ]);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMedia::class, ['media_id' => $media->id, 'creator' => 'Michael Curtiz']);

        $response->assertHasErrors(["media_id {$legacy->id}"]);
        expect($media->refresh()->creator->name)->toBe('Someone Else')
            ->and(Creator::count())->toBe(1);
    });

    test('refuses renaming an item recorded with no creator onto an item that has one', function () {
        /** @var TestCase $this */
        $withCreator = Media::factory()->book()->create([
            'title' => 'Dune',
            'creator_id' => Creator::factory()->create(['name' => 'Frank Herbert']),
        ]);
        $legacy = Media::factory()->book()->create(['title' => 'Doon', 'creator_id' => null]);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMedia::class, ['media_id' => $legacy->id, 'title' => 'Dune']);

        $response->assertHasErrors([
            "media_id {$withCreator->id}, \"Dune\" by Frank Herbert, has the same title and media type, and this item has no creator",
            'give this item its creator in the same edit',
        ]);
        expect($legacy->refresh()->title)->toBe('Doon');
    });

    test('allows the rename once the item recorded with no creator is given a different one', function () {
        /** @var TestCase $this */
        // The resolution the refusal suggests: two different works, told
        // apart by filling in the creator the older item was missing.
        $legacy = Media::factory()->movie()->create(['title' => 'Dune', 'creator_id' => null]);
        $media = Media::factory()->movie()->create([
            'title' => 'Dune: Part One',
            'creator_id' => Creator::factory()->create(['name' => 'Denis Villeneuve']),
        ]);
        $admin = User::factory()->create(['is_admin' => true]);

        AdminServer::actingAs($admin)->tool(EditMedia::class, ['media_id' => $legacy->id, 'creator' => 'David Lynch'])->assertOk();
        AdminServer::actingAs($admin)->tool(EditMedia::class, ['media_id' => $media->id, 'title' => 'Dune'])->assertOk();

        expect($media->refresh()->title)->toBe('Dune');
    });
});

describe('handle() validation', function () {
    test('rejects a media id that does not exist', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMedia::class, ['media_id' => 999999, 'title' => 'Dune']);

        $response->assertHasErrors(['media id']);
    });

    test('rejects an empty title, since a title cannot be cleared', function () {
        /** @var TestCase $this */
        $media = duneBook();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMedia::class, ['media_id' => $media->id, 'title' => '']);

        $response->assertHasErrors(['title']);
        expect($media->refresh()->title)->toBe('Dune');
    });

    test('rejects an unknown media type', function () {
        /** @var TestCase $this */
        $media = duneBook();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMedia::class, ['media_id' => $media->id, 'media_type' => 'podcast']);

        $response->assertHasErrors(['media type']);
    });

    test('rejects a year that is not an integer', function () {
        /** @var TestCase $this */
        $media = duneBook();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMedia::class, ['media_id' => $media->id, 'year' => 'nineteen sixty-five']);

        $response->assertHasErrors(['year']);
        expect($media->refresh()->year)->toBe(1965);
    });
});

describe('annotations', function () {
    test('advertises a destructive write that is not idempotent', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->withToken(accessTokenFor($admin, ['mcp:use']))
            ->postJson('/mcp/admin', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);

        $tool = collect($response->json('result.tools'))->firstWhere('name', 'edit-media');

        // Not idempotent, because append_to_remark accumulates.
        expect($tool['annotations'])->toBe([
            'destructiveHint' => true,
        ]);
    });
});
