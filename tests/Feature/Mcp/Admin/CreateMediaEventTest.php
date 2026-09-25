<?php

use App\Enum\MediaEventTypeName;
use App\Mcp\Servers\AdminServer;
use App\Mcp\Tools\Admin\CreateMediaEvent;
use App\Models\Media;
use App\Models\MediaEvent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Tests\TestCase;

describe('shouldRegister()', function () {
    test('an anonymous caller cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $media = Media::factory()->create();

        $response = AdminServer::tool(CreateMediaEvent::class, [
            'media_id' => $media->id,
            'event_type' => 'finished',
            'occurred_on' => '2026-03-15',
        ]);

        $response->assertHasErrors(['Tool [create-media-event] not found']);
        expect(MediaEvent::count())->toBe(0);
    });

    test('a non-admin cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $media = Media::factory()->create();
        $user = User::factory()->create(['is_admin' => false]);

        $response = AdminServer::actingAs($user)->tool(CreateMediaEvent::class, [
            'media_id' => $media->id,
            'event_type' => 'finished',
            'occurred_on' => '2026-03-15',
        ]);

        $response->assertHasErrors(['Tool [create-media-event] not found']);
        expect(MediaEvent::count())->toBe(0);
    });
});

describe('handle()', function () {
    test('refuses a non-admin, even if it is reached', function () {
        /** @var TestCase $this */
        // Called directly, since going through the server would only prove
        // shouldRegister works. handle() must refuse on its own too.
        $media = Media::factory()->create();
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $response = (new CreateMediaEvent)->handle(new Request([
            'media_id' => $media->id,
            'event_type' => 'finished',
            'occurred_on' => '2026-03-15',
        ]));

        expect($response->isError())->toBeTrue()
            ->and((string) $response->content())->toBe('You are not authorized to log media events.')
            ->and(MediaEvent::count())->toBe(0);
    });

    test('refuses a caller who may not read media events', function () {
        /** @var TestCase $this */
        // Every ability is admin-only today, so the refusal is forced here to
        // prove the check runs: the response returns other events' comments.
        Gate::before(fn (User $user, string $ability) => $ability === 'viewAny' ? false : null);
        $media = Media::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(CreateMediaEvent::class, [
            'media_id' => $media->id,
            'event_type' => 'finished',
            'occurred_on' => '2026-03-15',
        ]);

        $response->assertHasErrors(['You are not authorized to read media events.']);
        expect(MediaEvent::count())->toBe(0);
    });

    test('logs the event', function () {
        /** @var TestCase $this */
        $media = Media::factory()->book()->create(['title' => 'Dune']);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(CreateMediaEvent::class, [
            'media_id' => $media->id,
            'event_type' => 'finished',
            'occurred_on' => '2026-03-15',
            'comment' => 'Loved the ending',
        ]);

        $response->assertOk();

        $event = MediaEvent::sole();
        expect($event->media_id)->toBe($media->id)
            ->and($event->mediaEventType->name)->toBe(MediaEventTypeName::FINISHED)
            ->and($event->occurred_at->toIso8601String())->toBe('2026-03-15T12:00:00+00:00')
            ->and($event->comment)->toBe('Loved the ending');

        $response->assertStructuredContent([
            'event_id' => $event->id,
            'media_id' => $media->id,
            'title' => 'Dune',
            'event_type' => 'finished',
            'occurred_at' => '2026-03-15T12:00:00+00:00',
            'comment' => 'Loved the ending',
            'other_events_of_type' => [],
        ]);
    });

    test('changes the item\'s status', function () {
        /** @var TestCase $this */
        $media = Media::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        AdminServer::actingAs($admin)->tool(CreateMediaEvent::class, [
            'media_id' => $media->id,
            'event_type' => 'started',
            'occurred_on' => '2026-03-15',
        ])->assertOk();

        expect(DB::table('media_tracking_summary')->where('media_id', $media->id)->value('current_status'))
            ->toBe('started');
    });

    test('stores the date at noon UTC', function () {
        /** @var TestCase $this */
        $media = Media::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(CreateMediaEvent::class, [
            'media_id' => $media->id,
            'event_type' => 'started',
            'occurred_on' => '2026-03-15',
        ]);

        $response->assertStructuredContent(function ($json) {
            $json->where('occurred_at', '2026-03-15T12:00:00+00:00')
                ->where('comment', null)
                ->etc();
        });
        expect(MediaEvent::sole()->occurred_at->toIso8601String())->toBe('2026-03-15T12:00:00+00:00');
    });

    test('logs a second event of the same type and reports the first', function () {
        /** @var TestCase $this */
        $media = Media::factory()->create();
        $earlier = MediaEvent::factory()->finished()->withComment('First read')
            ->at(Carbon::parse('2020-06-01 12:00:00'))
            ->create(['media_id' => $media->id]);
        MediaEvent::factory()->started()->at(Carbon::parse('2020-05-01 12:00:00'))->create(['media_id' => $media->id]);
        MediaEvent::factory()->finished()->create(); // another item's
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(CreateMediaEvent::class, [
            'media_id' => $media->id,
            'event_type' => 'finished',
            'occurred_on' => '2026-03-15',
        ]);

        $response->assertOk();
        $response->assertStructuredContent(function ($json) use ($earlier) {
            $json->where('other_events_of_type', [[
                'event_id' => $earlier->id,
                'occurred_at' => '2020-06-01T12:00:00+00:00',
                'comment' => 'First read',
            ]])->etc();
        });

        expect($media->events()->count())->toBe(3);
    });

    test('is not idempotent', function () {
        /** @var TestCase $this */
        $media = Media::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $arguments = ['media_id' => $media->id, 'event_type' => 'comment', 'occurred_on' => '2026-03-15', 'comment' => 'Hm'];

        AdminServer::actingAs($admin)->tool(CreateMediaEvent::class, $arguments)->assertOk();
        AdminServer::actingAs($admin)->tool(CreateMediaEvent::class, $arguments)->assertOk();

        expect(MediaEvent::count())->toBe(2);
    });

    test('rejects a media id that does not exist', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(CreateMediaEvent::class, [
            'media_id' => 999999,
            'event_type' => 'finished',
            'occurred_on' => '2026-03-15',
        ]);

        $response->assertHasErrors(['media id']);
        expect(MediaEvent::count())->toBe(0);
    });

    test('rejects an unknown event type', function () {
        /** @var TestCase $this */
        $media = Media::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(CreateMediaEvent::class, [
            'media_id' => $media->id,
            'event_type' => 'backlog',
            'occurred_on' => '2026-03-15',
        ]);

        $response->assertHasErrors(['event type']);
        expect(MediaEvent::count())->toBe(0);
    });

    test('rejects anything but a YYYY-MM-DD date', function (string $occurredOn) {
        /** @var TestCase $this */
        $media = Media::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(CreateMediaEvent::class, [
            'media_id' => $media->id,
            'event_type' => 'finished',
            'occurred_on' => $occurredOn,
        ]);

        $response->assertHasErrors(['occurred on']);
        expect(MediaEvent::count())->toBe(0);
    })->with([
        'relative' => 'yesterday',
        'weekday' => 'last Saturday',
        'prose' => 'March 15, 2026',
        'impossible' => '2026-02-30',
        'with a time' => '2026-03-15T20:30:00Z',
    ]);

    test('requires a date', function () {
        /** @var TestCase $this */
        $media = Media::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(CreateMediaEvent::class, [
            'media_id' => $media->id,
            'event_type' => 'finished',
        ]);

        $response->assertHasErrors(['occurred on']);
        expect(MediaEvent::count())->toBe(0);
    });

    test('rejects an empty comment', function () {
        /** @var TestCase $this */
        $media = Media::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(CreateMediaEvent::class, [
            'media_id' => $media->id,
            'event_type' => 'finished',
            'occurred_on' => '2026-03-15',
            'comment' => '',
        ]);

        $response->assertHasErrors(['comment']);
        expect(MediaEvent::count())->toBe(0);
    });
});

describe('annotations', function () {
    test('advertises a non-destructive write that is not idempotent', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->withToken(accessTokenFor($admin, ['mcp:use']))
            ->postJson('/mcp/admin', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);

        $tool = collect($response->json('result.tools'))->firstWhere('name', 'create-media-event');

        // idempotentHint is omitted rather than false: laravel/mcp only emits
        // the annotations a tool declares, and the spec's default is false.
        expect($tool['annotations'])->toBe([
            'destructiveHint' => false,
        ]);
    });
});
