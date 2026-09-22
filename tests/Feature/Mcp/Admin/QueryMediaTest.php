<?php

use App\Mcp\Servers\AdminServer;
use App\Mcp\Tools\Admin\QueryMedia;
use App\Models\Media;
use App\Models\MediaEvent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Tests\TestCase;

describe('shouldRegister()', function () {
    test('an anonymous caller cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $response = AdminServer::tool(QueryMedia::class);

        // "Tool not found" rather than "denied": tools/call resolves through
        // the same filtered collection tools/list does, so a tool the caller
        // cannot register simply is not there.
        $response->assertHasErrors(['Tool [query-media] not found']);
    });

    test('a non-admin cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $user = User::factory()->create(['is_admin' => false]);

        $response = AdminServer::actingAs($user)->tool(QueryMedia::class);

        $response->assertHasErrors(['Tool [query-media] not found']);
    });

    test('an admin can reach the tool', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(QueryMedia::class);

        $response->assertOk();
    });
});

describe('handle()', function () {
    test('withholds remarks from a non-admin, even if it is reached', function () {
        /** @var TestCase $this */
        // Called directly, since going through the server would only prove
        // shouldRegister works. The point is that handle() refuses on its own
        // too, so registering this tool somewhere it does not belong leaks
        // nothing.
        Media::factory()->book()->create(['note' => 'PRIVATE-REMARK-MARKER']);
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $response = (new QueryMedia)->handle(new Request);

        expect($response->isError())->toBeTrue()
            ->and((string) $response->content())->toBe('You are not authorized to read David\'s remarks.')
            ->and((string) $response->content())->not->toContain('PRIVATE-REMARK-MARKER');
    });

    test('returns the remark', function () {
        /** @var TestCase $this */
        Media::factory()->book()->create(['title' => 'Dune', 'note' => 'Worth the hype']);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(QueryMedia::class, ['title' => 'Dune']);

        $response->assertOk();
        $response->assertStructuredContent(function ($json) {
            $json->where('results.0.title', 'Dune')
                ->where('results.0.remark', 'Worth the hype')
                ->etc();
        });
    });

    test('returns a null remark when David has not written one', function () {
        /** @var TestCase $this */
        Media::factory()->book()->create(['title' => 'Dune', 'note' => null]);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(QueryMedia::class, ['title' => 'Dune']);

        $response->assertOk();
        $response->assertStructuredContent(function ($json) {
            $json->where('results.0.remark', null)->etc();
        });
    });

    test('omits history unless asked', function () {
        /** @var TestCase $this */
        // Omitted rather than null: a null history would read as "no events",
        // which is a different claim from "not fetched".
        $media = Media::factory()->book()->create(['title' => 'Dune', 'note' => null]);
        MediaEvent::factory()->for($media)->finished()->withComment('PRIVATE-COMMENT-MARKER')->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(QueryMedia::class, ['title' => 'Dune']);

        $response->assertOk();
        $response->assertDontSee('PRIVATE-COMMENT-MARKER');
        $response->assertStructuredContent(
            fn ($json) => $json->has('results.0', fn ($item) => $item->missing('history')->etc())->etc()
        );
    });

    test('returns the event timeline when asked', function () {
        /** @var TestCase $this */
        $media = Media::factory()->book()->create(['title' => 'Dune', 'note' => null]);
        MediaEvent::factory()->for($media)->started()->at(Carbon::create(2024, 1, 1))->create();
        MediaEvent::factory()->for($media)->finished()->at(Carbon::create(2024, 2, 1))
            ->withComment('Worth the hype')->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(QueryMedia::class, [
            'title' => 'Dune',
            'include_history' => true,
        ]);

        $response->assertOk();
        $response->assertStructuredContent(function ($json) {
            $json->where('results.0.history.0.type', 'started')
                ->where('results.0.history.1.type', 'finished')
                ->where('results.0.history.1.comment', 'Worth the hype')
                ->etc();
        });
    });

    test('searches the free text with the text filter', function () {
        /** @var TestCase $this */
        Media::factory()->book()->create(['title' => 'A Match', 'note' => 'Utterly disappointing']);
        Media::factory()->book()->create(['title' => 'Not A Match', 'note' => 'Wonderful']);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(QueryMedia::class, ['text' => 'disappointing']);

        $response->assertOk();
        $response->assertStructuredContent(function ($json) {
            $json->where('total', 1)
                ->where('results.0.title', 'A Match')
                ->etc();
        });
    });

    test('never returns full_text, even alongside the remark and history', function () {
        /** @var TestCase $this */
        $media = Media::factory()->book()->create(['title' => 'Dune', 'note' => 'A remark']);
        MediaEvent::factory()->for($media)->finished()->withComment('A comment')->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(QueryMedia::class, [
            'title' => 'Dune',
            'include_history' => true,
        ]);

        $response->assertOk();
        $response->assertStructuredContent(
            fn ($json) => $json->has('results.0', fn ($item) => $item->missing('full_text')->etc())->etc()
        );
    });

    test('rejects a non-boolean include_history', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(QueryMedia::class, ['include_history' => 'yes please']);

        $response->assertHasErrors(['include history']);
    });

    test('rejects a limit above the maximum', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(QueryMedia::class, ['limit' => 101]);

        $response->assertHasErrors(['limit']);
    });
});
