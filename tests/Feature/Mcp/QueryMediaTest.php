<?php

use App\Mcp\Servers\PublicServer;
use App\Mcp\Tools\QueryMedia;
use App\Models\Creator;
use App\Models\Media;
use App\Models\MediaEvent;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Carbon;

test('returns the whole library when called without arguments', function () {
    /** @var TestCase $this */
    Media::factory()->book()->create(['title' => 'A Book']);
    Media::factory()->movie()->create(['title' => 'A Movie']);

    $response = PublicServer::tool(QueryMedia::class);

    $response->assertOk();
    $response->assertStructuredContent(function ($json) {
        $json->where('total', 2)->where('has_more_pages', false)->etc();
    });
});

test('includes the public tracking fields for each item', function () {
    /** @var TestCase $this */
    $creator = Creator::factory()->create(['name' => 'Frank Herbert']);
    $media = Media::factory()->book()
        ->has(MediaEvent::factory()->started()->at(Carbon::create(2024, 1, 5)), 'events')
        ->has(MediaEvent::factory()->finished()->at(Carbon::create(2024, 2, 10)), 'events')
        ->create(['title' => 'Dune', 'year' => 1965, 'creator_id' => $creator->id]);

    $response = PublicServer::tool(QueryMedia::class);

    $response->assertOk();
    $response->assertStructuredContent(function ($json) use ($media) {
        $json->where('results.0.media_id', $media->id)
            ->where('results.0.creator_id', $media->creator_id)
            ->where('results.0.title', 'Dune')
            ->where('results.0.year', 1965)
            ->where('results.0.media_type', 'book')
            ->where('results.0.creator', 'Frank Herbert')
            ->where('results.0.current_status', 'finished')
            ->whereType('results.0.started_at', 'string')
            ->whereType('results.0.finished_at', 'string')
            ->where('results.0.abandoned_at', null)
            ->etc();
    });
});

describe('filters', function () {
    test('by title', function () {
        /** @var TestCase $this */
        Media::factory()->book()->create(['title' => 'Dune']);
        Media::factory()->book()->create(['title' => 'Foundation']);

        $response = PublicServer::tool(QueryMedia::class, ['title' => 'dune']);

        $response->assertStructuredContent(function ($json) {
            $json->where('total', 1)->where('results.0.title', 'Dune')->etc();
        });
    });

    test('by creator', function () {
        /** @var TestCase $this */
        $herbert = Creator::factory()->create(['name' => 'Frank Herbert']);
        Media::factory()->book()->create(['title' => 'Dune', 'creator_id' => $herbert->id]);
        Media::factory()->book()->create(['title' => 'Foundation']);

        $response = PublicServer::tool(QueryMedia::class, ['creator' => 'herbert']);

        $response->assertStructuredContent(function ($json) {
            $json->where('total', 1)->where('results.0.title', 'Dune')->etc();
        });
    });

    test('by media type', function () {
        /** @var TestCase $this */
        Media::factory()->book()->create(['title' => 'Dune']);
        Media::factory()->movie()->create(['title' => 'Dune']);

        $response = PublicServer::tool(QueryMedia::class, ['media_type' => 'movie']);

        $response->assertStructuredContent(function ($json) {
            $json->where('total', 1)->where('results.0.media_type', 'movie')->etc();
        });
    });

    test('by status', function () {
        /** @var TestCase $this */
        Media::factory()->book()->create(['title' => 'Backlog Book']);
        Media::factory()->book()
            ->has(MediaEvent::factory()->started(), 'events')
            ->create(['title' => 'Started Book']);

        $response = PublicServer::tool(QueryMedia::class, ['status' => 'backlog']);

        $response->assertStructuredContent(function ($json) {
            $json->where('total', 1)->where('results.0.title', 'Backlog Book')->etc();
        });
    });

    test('release year is distinct from finished year', function () {
        /** @var TestCase $this */
        Media::factory()->book()
            ->has(MediaEvent::factory()->finished()->at(Carbon::create(2025, 3, 1)), 'events')
            ->create(['title' => 'Dune', 'year' => 1965]);

        PublicServer::tool(QueryMedia::class, ['year' => 1965])
            ->assertStructuredContent(fn ($json) => $json->where('total', 1)->etc());

        PublicServer::tool(QueryMedia::class, ['finished_year' => 2025])
            ->assertStructuredContent(fn ($json) => $json->where('total', 1)->etc());

        PublicServer::tool(QueryMedia::class, ['year' => 2025])
            ->assertStructuredContent(fn ($json) => $json->where('total', 0)->etc());
    });

    test('by started year', function () {
        /** @var TestCase $this */
        Media::factory()->book()
            ->has(MediaEvent::factory()->started()->at(Carbon::create(2023, 3, 1)), 'events')
            ->create(['title' => 'Started In 2023']);
        Media::factory()->book()
            ->has(MediaEvent::factory()->started()->at(Carbon::create(2024, 3, 1)), 'events')
            ->create(['title' => 'Started In 2024']);

        $response = PublicServer::tool(QueryMedia::class, ['started_year' => 2024]);

        $response->assertStructuredContent(function ($json) {
            $json->where('total', 1)->where('results.0.title', 'Started In 2024')->etc();
        });
    });

    test('combine with AND', function () {
        /** @var TestCase $this */
        Media::factory()->book()
            ->has(MediaEvent::factory()->finished()->at(Carbon::create(2025, 1, 1)), 'events')
            ->create(['title' => 'Finished Book']);
        Media::factory()->movie()
            ->has(MediaEvent::factory()->finished()->at(Carbon::create(2025, 2, 1)), 'events')
            ->create(['title' => 'Finished Movie']);
        Media::factory()->book()->create(['title' => 'Backlog Book']);

        $response = PublicServer::tool(QueryMedia::class, [
            'media_type' => 'book',
            'status' => 'finished',
            'finished_year' => 2025,
        ]);

        $response->assertStructuredContent(function ($json) {
            $json->where('total', 1)->where('results.0.title', 'Finished Book')->etc();
        });
    });
});

describe('privacy', function () {
    test('never returns the admin-only note field', function () {
        /** @var TestCase $this */
        Media::factory()->book()->create([
            'title' => 'A Book',
            'note' => 'PRIVATE-NOTE-MARKER',
        ]);

        $response = PublicServer::tool(QueryMedia::class);

        $response->assertOk();
        $response->assertDontSee('PRIVATE-NOTE-MARKER');
    });

    test('never returns admin-only event comments', function () {
        /** @var TestCase $this */
        Media::factory()->book()
            ->has(MediaEvent::factory()->finished()->state(['comment' => 'PRIVATE-COMMENT-MARKER']), 'events')
            ->create(['title' => 'A Book', 'note' => null]);

        $response = PublicServer::tool(QueryMedia::class);

        $response->assertOk();
        $response->assertDontSee('PRIVATE-COMMENT-MARKER');
    });
});

describe('sorting', function () {
    test('defaults to recently finished when filtering finished items', function () {
        /** @var TestCase $this */
        Media::factory()->book()
            ->has(MediaEvent::factory()->finished()->at(Carbon::create(2023, 1, 1)), 'events')
            ->create(['title' => 'Finished Earlier']);
        Media::factory()->book()
            ->has(MediaEvent::factory()->finished()->at(Carbon::create(2024, 1, 1)), 'events')
            ->create(['title' => 'Finished Recently']);

        $response = PublicServer::tool(QueryMedia::class, ['status' => 'finished']);

        $response->assertStructuredContent(function ($json) {
            $json->where('results.0.title', 'Finished Recently')
                ->where('results.1.title', 'Finished Earlier')
                ->etc();
        });
    });

    test('honors an explicit sort', function () {
        /** @var TestCase $this */
        Media::factory()->book()->create(['title' => 'Zebra']);
        Media::factory()->book()->create(['title' => 'Aardvark']);
        Media::factory()->book()->create(['title' => 'Monkey']);

        $response = PublicServer::tool(QueryMedia::class, ['sort' => 'title']);

        $response->assertStructuredContent(function ($json) {
            $json->where('results.0.title', 'Aardvark')
                ->where('results.1.title', 'Monkey')
                ->where('results.2.title', 'Zebra')
                ->etc();
        });
    });
});

describe('pagination', function () {
    test('paginates results', function () {
        /** @var TestCase $this */
        Media::factory()->book()->count(3)->create();

        $response = PublicServer::tool(QueryMedia::class, ['limit' => 2, 'page' => 2]);

        $response->assertStructuredContent(function ($json) {
            $json->where('total', 3)
                ->where('page', 2)
                ->where('limit', 2)
                ->where('has_more_pages', false)
                ->has('results', 1)
                ->etc();
        });
    });

    test('walking every page exhausts the full library exactly once', function () {
        /** @var TestCase $this */
        $created = Media::factory()->book()->count(5)->create();

        $seen = [];
        $page = 1;

        do {
            $hasMore = null;

            PublicServer::tool(QueryMedia::class, ['limit' => 2, 'page' => $page])
                ->assertOk()
                ->assertStructuredContent(function ($json) use (&$seen, &$hasMore, $page) {
                    $json->where('total', 5)
                        ->where('page', $page)
                        ->where('limit', 2)
                        ->where('results', function ($results) use (&$seen) {
                            $seen = array_merge($seen, $results->pluck('media_id')->all());

                            return true;
                        })
                        ->where('has_more_pages', function ($value) use (&$hasMore) {
                            $hasMore = $value;

                            return true;
                        })
                        ->etc();
                });

            $page++;
        } while ($hasMore && $page <= 5);

        expect($hasMore)->toBeFalse();
        expect($seen)->toEqualCanonicalizing($created->pluck('id')->all());
    });

    test('rejects a limit above the maximum', function () {
        /** @var TestCase $this */
        $response = PublicServer::tool(QueryMedia::class, ['limit' => 101]);

        $response->assertHasErrors(['limit']);
    });
});

describe('validation', function () {
    test('rejects an invalid media type', function () {
        /** @var TestCase $this */
        $response = PublicServer::tool(QueryMedia::class, ['media_type' => 'podcast']);

        $response->assertHasErrors(['media type']);
    });

    test('rejects an invalid status', function () {
        /** @var TestCase $this */
        $response = PublicServer::tool(QueryMedia::class, ['status' => 'paused']);

        $response->assertHasErrors(['status']);
    });

    test('rejects an invalid sort', function () {
        /** @var TestCase $this */
        $response = PublicServer::tool(QueryMedia::class, ['sort' => 'random']);

        $response->assertHasErrors(['sort']);
    });
});
