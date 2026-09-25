<?php

use App\Enum\MediaEventTypeName;
use App\Mcp\Servers\AdminServer;
use App\Mcp\Tools\Admin\EditMediaEvent;
use App\Models\Media;
use App\Models\MediaEvent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Tests\TestCase;

/**
 * A finished event on Dune with every editable field set, so a test can prove
 * the fields it does not pass are left alone.
 */
function duneFinishedEvent(): MediaEvent
{
    return MediaEvent::factory()->finished()
        ->withComment('Loved the ending')
        ->at(Carbon::parse('2026-03-15 12:00:00', 'UTC'))
        ->create(['media_id' => Media::factory()->book()->create(['title' => 'Dune'])]);
}

describe('shouldRegister()', function () {
    test('an anonymous caller cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $event = duneFinishedEvent();

        $response = AdminServer::tool(EditMediaEvent::class, ['event_id' => $event->id, 'occurred_on' => '2026-03-16']);

        $response->assertHasErrors(['Tool [edit-media-event] not found']);
        expect($event->refresh()->occurred_at->toDateString())->toBe('2026-03-15');
    });

    test('a non-admin cannot reach the tool at all', function () {
        /** @var TestCase $this */
        $event = duneFinishedEvent();
        $user = User::factory()->create(['is_admin' => false]);

        $response = AdminServer::actingAs($user)->tool(EditMediaEvent::class, ['event_id' => $event->id, 'occurred_on' => '2026-03-16']);

        $response->assertHasErrors(['Tool [edit-media-event] not found']);
        expect($event->refresh()->occurred_at->toDateString())->toBe('2026-03-15');
    });
});

describe('handle()', function () {
    test('refuses a non-admin, even if it is reached', function () {
        /** @var TestCase $this */
        // Called directly, since going through the server would only prove
        // shouldRegister works. handle() must refuse on its own too.
        $event = duneFinishedEvent();
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $response = (new EditMediaEvent)->handle(new Request(['event_id' => $event->id, 'occurred_on' => '2026-03-16']));

        expect($response->isError())->toBeTrue()
            ->and((string) $response->content())->toBe('You are not authorized to edit this event.')
            ->and($event->refresh()->occurred_at->toDateString())->toBe('2026-03-15');
    });

    test('refuses a caller who may not update this event', function () {
        /** @var TestCase $this */
        // Every ability is admin-only today, so the refusal is forced here to
        // prove handle() asks MediaEventPolicy::update about this event.
        $event = duneFinishedEvent();
        Gate::before(fn (User $user, string $ability, array $arguments) => $ability === 'update' && ($arguments[0] ?? null)?->is($event) ? false : null);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMediaEvent::class, ['event_id' => $event->id, 'occurred_on' => '2026-03-16']);

        $response->assertHasErrors(['You are not authorized to edit this event.']);
        expect($event->refresh()->occurred_at->toDateString())->toBe('2026-03-15');
    });

    test('refuses a caller who may not read this event, even when the edit leaves the comment alone', function () {
        /** @var TestCase $this */
        // The response returns the comment, so the check runs whatever the
        // call asks for.
        $event = duneFinishedEvent();
        Gate::before(fn (User $user, string $ability) => $ability === 'view' ? false : null);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMediaEvent::class, ['event_id' => $event->id, 'occurred_on' => '2026-03-16']);

        $response->assertHasErrors(['You are not authorized to read this event.']);
        expect($event->refresh()->occurred_at->toDateString())->toBe('2026-03-15');
    });

    test('changes only the fields it is given', function () {
        /** @var TestCase $this */
        $event = duneFinishedEvent();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMediaEvent::class, ['event_id' => $event->id, 'occurred_on' => '2026-03-16']);

        $response->assertOk();
        $response->assertStructuredContent([
            'event_id' => $event->id,
            'media_id' => $event->media_id,
            'title' => 'Dune',
            'event_type' => 'finished',
            'occurred_at' => '2026-03-16T12:00:00+00:00',
            'comment' => 'Loved the ending',
            'moved_from' => null,
            'changed_fields' => ['occurred_on'],
        ]);

        $event->refresh();
        expect($event->occurred_at->toIso8601String())->toBe('2026-03-16T12:00:00+00:00')
            ->and($event->mediaEventType->name)->toBe(MediaEventTypeName::FINISHED)
            ->and($event->comment)->toBe('Loved the ending');
    });

    test('corrects the event type, and with it the item\'s status', function () {
        /** @var TestCase $this */
        $event = duneFinishedEvent();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMediaEvent::class, ['event_id' => $event->id, 'event_type' => 'abandoned']);

        $response->assertOk();
        $response->assertStructuredContent(function ($json) {
            $json->where('event_type', 'abandoned')
                ->where('changed_fields', ['event_type'])
                ->etc();
        });

        $summary = DB::table('media_tracking_summary')->where('media_id', $event->media_id)->first();
        expect($summary->current_status)->toBe('abandoned')
            ->and($summary->finished_at)->toBeNull();
    });

    test('corrects a mistaken start date that logging another event could not', function () {
        /** @var TestCase $this */
        $media = Media::factory()->create();
        $mistyped = MediaEvent::factory()->started()->at(Carbon::parse('2016-03-15 12:00:00', 'UTC'))->create(['media_id' => $media->id]);
        $admin = User::factory()->create(['is_admin' => true]);

        AdminServer::actingAs($admin)->tool(EditMediaEvent::class, ['event_id' => $mistyped->id, 'occurred_on' => '2026-03-15'])
            ->assertOk();

        $startedAt = DB::table('media_tracking_summary')->where('media_id', $media->id)->value('started_at');
        expect(Carbon::parse($startedAt)->toDateString())->toBe('2026-03-15');
    });

    test('stores a corrected date at noon UTC, whatever time the event had', function () {
        /** @var TestCase $this */
        $event = MediaEvent::factory()->started()->at(Carbon::parse('2026-03-15 20:45:00', 'UTC'))->create();
        $admin = User::factory()->create(['is_admin' => true]);

        AdminServer::actingAs($admin)->tool(EditMediaEvent::class, ['event_id' => $event->id, 'occurred_on' => '2026-03-14'])
            ->assertOk();

        expect($event->refresh()->occurred_at->toIso8601String())->toBe('2026-03-14T12:00:00+00:00');
    });

    test('reports no changed fields when every value matches what is stored', function () {
        /** @var TestCase $this */
        $event = duneFinishedEvent();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMediaEvent::class, [
            'event_id' => $event->id,
            'media_id' => $event->media_id,
            'event_type' => 'finished',
            'occurred_on' => '2026-03-15',
            'replace_comment' => 'Loved the ending',
        ]);

        $response->assertOk();
        $response->assertStructuredContent(function ($json) {
            $json->where('changed_fields', [])
                ->where('moved_from', null)
                ->etc();
        });
    });

    test('refuses a call that names no field to change', function () {
        /** @var TestCase $this */
        $event = duneFinishedEvent();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMediaEvent::class, ['event_id' => $event->id]);

        $response->assertHasErrors(['Nothing to change']);
    });
});

describe('handle() move', function () {
    test('moves the event to another item and names the one it came from', function () {
        /** @var TestCase $this */
        $event = duneFinishedEvent();
        $from = $event->media;
        $to = Media::factory()->book()->create(['title' => 'Dune Messiah']);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMediaEvent::class, ['event_id' => $event->id, 'media_id' => $to->id]);

        $response->assertOk();
        $response->assertStructuredContent([
            'event_id' => $event->id,
            'media_id' => $to->id,
            'title' => 'Dune Messiah',
            'event_type' => 'finished',
            'occurred_at' => '2026-03-15T12:00:00+00:00',
            'comment' => 'Loved the ending',
            'moved_from' => ['media_id' => $from->id, 'title' => 'Dune'],
            'changed_fields' => ['media_id'],
        ]);

        expect($event->refresh()->media_id)->toBe($to->id)
            ->and($from->events()->count())->toBe(0);

        $statuses = DB::table('media_tracking_summary')->whereIn('media_id', [$from->id, $to->id])->pluck('current_status', 'media_id');
        expect($statuses[$from->id])->toBe('backlog')
            ->and($statuses[$to->id])->toBe('finished');
    });

    test('moves and corrects in one call', function () {
        /** @var TestCase $this */
        $event = duneFinishedEvent();
        $to = Media::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMediaEvent::class, [
            'event_id' => $event->id,
            'media_id' => $to->id,
            'event_type' => 'started',
        ]);

        $response->assertStructuredContent(function ($json) {
            $json->where('changed_fields', ['media_id', 'event_type'])->etc();
        });
    });

    test('rejects a media id that does not exist', function () {
        /** @var TestCase $this */
        $event = duneFinishedEvent();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMediaEvent::class, ['event_id' => $event->id, 'media_id' => 999999]);

        $response->assertHasErrors(['media id']);
        expect($event->refresh()->media->title)->toBe('Dune');
    });

    test('rejects an empty media id, since an event always belongs to an item', function () {
        /** @var TestCase $this */
        $event = duneFinishedEvent();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMediaEvent::class, ['event_id' => $event->id, 'media_id' => '']);

        $response->assertHasErrors(['media id']);
        expect($event->refresh()->media->title)->toBe('Dune');
    });
});

describe('handle() comment', function () {
    test('replaces the comment', function () {
        /** @var TestCase $this */
        $event = duneFinishedEvent();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMediaEvent::class, ['event_id' => $event->id, 'replace_comment' => 'Actually, the ending dragged']);

        $response->assertStructuredContent(function ($json) {
            $json->where('comment', 'Actually, the ending dragged')
                ->where('changed_fields', ['comment'])
                ->etc();
        });
        expect($event->refresh()->comment)->toBe('Actually, the ending dragged');
    });

    test('clears the comment with an empty replace', function () {
        /** @var TestCase $this */
        $event = duneFinishedEvent();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMediaEvent::class, ['event_id' => $event->id, 'replace_comment' => '']);

        $response->assertStructuredContent(function ($json) {
            $json->where('comment', null)->etc();
        });
        expect($event->refresh()->comment)->toBeNull();
    });

    test('appends to the comment on a new line and returns the whole of it', function () {
        /** @var TestCase $this */
        $event = duneFinishedEvent();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMediaEvent::class, ['event_id' => $event->id, 'append_to_comment' => 'Read it in two days']);

        $response->assertStructuredContent(function ($json) {
            $json->where('comment', "Loved the ending\nRead it in two days")->etc();
        });
        expect($event->refresh()->comment)->toBe("Loved the ending\nRead it in two days");
    });

    test('appending to an empty comment sets it', function () {
        /** @var TestCase $this */
        $event = MediaEvent::factory()->finished()->withComment(null)->create();
        $admin = User::factory()->create(['is_admin' => true]);

        AdminServer::actingAs($admin)->tool(EditMediaEvent::class, ['event_id' => $event->id, 'append_to_comment' => 'Loved it'])
            ->assertOk();

        expect($event->refresh()->comment)->toBe('Loved it');
    });

    test('rejects an empty append', function () {
        /** @var TestCase $this */
        $event = duneFinishedEvent();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMediaEvent::class, ['event_id' => $event->id, 'append_to_comment' => '']);

        $response->assertHasErrors(['pass replace_comment as an empty string']);
        expect($event->refresh()->comment)->toBe('Loved the ending');
    });

    test('rejects replacing and appending at once', function () {
        /** @var TestCase $this */
        $event = duneFinishedEvent();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMediaEvent::class, [
            'event_id' => $event->id,
            'replace_comment' => 'New',
            'append_to_comment' => 'More',
        ]);

        $response->assertHasErrors(['replace comment']);
        expect($event->refresh()->comment)->toBe('Loved the ending');
    });
});

describe('handle() validation', function () {
    test('rejects an event id that does not exist', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMediaEvent::class, ['event_id' => 999999, 'occurred_on' => '2026-03-16']);

        $response->assertHasErrors(['event id']);
    });

    test('rejects an event type it does not know', function (string $eventType) {
        /** @var TestCase $this */
        $event = duneFinishedEvent();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMediaEvent::class, ['event_id' => $event->id, 'event_type' => $eventType]);

        $response->assertHasErrors(['event type']);
        expect($event->refresh()->mediaEventType->name)->toBe(MediaEventTypeName::FINISHED);
    })->with([
        'unknown' => 'backlog',
        'empty, since an event type cannot be cleared' => '',
    ]);

    test('rejects anything but a YYYY-MM-DD date', function (string $occurredOn) {
        /** @var TestCase $this */
        $event = duneFinishedEvent();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = AdminServer::actingAs($admin)->tool(EditMediaEvent::class, ['event_id' => $event->id, 'occurred_on' => $occurredOn]);

        $response->assertHasErrors(['occurred on']);
        expect($event->refresh()->occurred_at->toDateString())->toBe('2026-03-15');
    })->with([
        'relative' => 'yesterday',
        'prose' => 'March 16, 2026',
        'impossible' => '2026-02-30',
        'with a time' => '2026-03-16T20:30:00Z',
        'empty, since a date cannot be cleared' => '',
    ]);
});

describe('annotations', function () {
    test('advertises a destructive write that is not idempotent', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->withToken(accessTokenFor($admin, ['mcp:use']))
            ->postJson('/mcp/admin', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);

        $tool = collect($response->json('result.tools'))->firstWhere('name', 'edit-media-event');

        // Not idempotent, because append_to_comment accumulates.
        expect($tool['annotations'])->toBe([
            'destructiveHint' => true,
        ]);
    });
});
