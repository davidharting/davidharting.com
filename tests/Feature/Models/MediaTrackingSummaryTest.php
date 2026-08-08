<?php

use App\Models\Media;
use App\Models\MediaEvent;
use App\Models\MediaTrackingSummary;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Carbon;

/**
 * Read the summary row for one media item.
 */
function summaryFor(Media $media): MediaTrackingSummary
{
    return MediaTrackingSummary::query()
        ->where('media_id', $media->id)
        ->sole();
}

function fullTextFor(Media $media): ?string
{
    return summaryFor($media)->full_text;
}

describe('full_text', function () {
    test('is null when the item has no note and no commented events', function () {
        /** @var TestCase $this */
        $media = Media::factory()->book()
            ->has(MediaEvent::factory()->started()->state(['comment' => null]), 'events')
            ->create(['note' => null]);

        $this->assertNull(fullTextFor($media));
    });

    test('is null when the note and comments are only whitespace', function () {
        /** @var TestCase $this */
        $media = Media::factory()->book()
            ->has(MediaEvent::factory()->started()->state(['comment' => "  \n "]), 'events')
            ->create(['note' => '   ']);

        $this->assertNull(fullTextFor($media));
    });

    test('is the trimmed note when the item has no commented events', function () {
        /** @var TestCase $this */
        $media = Media::factory()->book()->create(['note' => "  Recommended by Ben.\n"]);

        $this->assertSame('Recommended by Ben.', fullTextFor($media));
    });

    test('renders a commented event as a markdown bullet with type and date', function () {
        /** @var TestCase $this */
        $media = Media::factory()->book()
            ->has(
                MediaEvent::factory()
                    ->finished()
                    ->at(Carbon::create(2025, 4, 1))
                    ->state(['comment' => 'Stuck the landing.']),
                'events'
            )
            ->create(['note' => null]);

        $this->assertSame(
            '- **finished** (2025-04-01): Stuck the landing.',
            fullTextFor($media)
        );
    });

    test('separates the note from the event bullets with a blank line', function () {
        /** @var TestCase $this */
        $media = Media::factory()->book()
            ->has(
                MediaEvent::factory()
                    ->started()
                    ->at(Carbon::create(2025, 3, 4))
                    ->state(['comment' => 'Slow first hundred pages.']),
                'events'
            )
            ->create(['note' => 'Recommended by Ben.']);

        $this->assertSame(
            "Recommended by Ben.\n\n- **started** (2025-03-04): Slow first hundred pages.",
            fullTextFor($media)
        );
    });

    test('lists every event type that carries a comment, oldest first', function () {
        /** @var TestCase $this */
        $media = Media::factory()->book()->create(['note' => null]);

        MediaEvent::factory()->for($media)->comment('Still thinking about the ending.')
            ->at(Carbon::create(2025, 5, 12))->create();
        MediaEvent::factory()->for($media)->started()
            ->at(Carbon::create(2025, 3, 4))->state(['comment' => 'Slow start.'])->create();
        MediaEvent::factory()->for($media)->abandoned()
            ->at(Carbon::create(2025, 6, 1))->state(['comment' => 'Gave up.'])->create();
        MediaEvent::factory()->for($media)->finished()
            ->at(Carbon::create(2025, 4, 1))->state(['comment' => 'Stuck the landing.'])->create();

        $this->assertSame(
            implode("\n", [
                '- **started** (2025-03-04): Slow start.',
                '- **finished** (2025-04-01): Stuck the landing.',
                '- **comment** (2025-05-12): Still thinking about the ending.',
                '- **abandoned** (2025-06-01): Gave up.',
            ]),
            fullTextFor($media)
        );
    });

    test('omits events that carry no comment', function () {
        /** @var TestCase $this */
        $media = Media::factory()->book()->create(['note' => null]);

        MediaEvent::factory()->for($media)->started()
            ->at(Carbon::create(2025, 3, 4))->state(['comment' => null])->create();
        MediaEvent::factory()->for($media)->finished()
            ->at(Carbon::create(2025, 4, 1))->state(['comment' => 'Stuck the landing.'])->create();

        $this->assertSame(
            '- **finished** (2025-04-01): Stuck the landing.',
            fullTextFor($media)
        );
    });

    test('indents a multi-line comment so it stays inside its bullet', function () {
        /** @var TestCase $this */
        $media = Media::factory()->book()
            ->has(
                MediaEvent::factory()
                    ->finished()
                    ->at(Carbon::create(2025, 4, 1))
                    ->state(['comment' => "First thought.\nSecond thought."]),
                'events'
            )
            ->create(['note' => null]);

        $this->assertSame(
            "- **finished** (2025-04-01): First thought.\n  Second thought.",
            fullTextFor($media)
        );
    });

    test('does not mix in text belonging to another media item', function () {
        /** @var TestCase $this */
        $media = Media::factory()->book()->create(['note' => 'Mine.']);
        Media::factory()->book()
            ->has(MediaEvent::factory()->finished()->state(['comment' => 'Theirs.']), 'events')
            ->create(['note' => 'Also theirs.']);

        $this->assertSame('Mine.', fullTextFor($media));
    });

    test('is searchable with a single case-insensitive LIKE across all free text', function () {
        /** @var TestCase $this */
        $noted = Media::factory()->book()->create(['note' => 'Borrowed from the library.']);
        $commented = Media::factory()->book()
            ->has(MediaEvent::factory()->finished()->state(['comment' => 'Returned to the LIBRARY late.']), 'events')
            ->create(['note' => null]);
        Media::factory()->book()->create(['note' => 'Bought it new.']);

        $matches = MediaTrackingSummary::query()
            ->whereLike('full_text', '%library%', caseSensitive: false)
            ->pluck('media_id');

        $this->assertEqualsCanonicalizing([$noted->id, $commented->id], $matches->all());
    });
});

describe('note', function () {
    test('is the item note on its own, without the event commentary', function () {
        /** @var TestCase $this */
        $media = Media::factory()->book()
            ->has(MediaEvent::factory()->finished()->state(['comment' => 'Stuck the landing.']), 'events')
            ->create(['note' => 'Recommended by Ben.']);

        $summary = summaryFor($media);

        $this->assertSame('Recommended by Ben.', $summary->note);
        $this->assertStringContainsString('Stuck the landing.', $summary->full_text);
        $this->assertStringNotContainsString('Stuck the landing.', $summary->note);
    });

    test('is trimmed, and null when blank', function () {
        /** @var TestCase $this */
        $trimmed = Media::factory()->book()->create(['note' => "  Recommended by Ben.\n"]);
        $blank = Media::factory()->book()->create(['note' => '   ']);
        $missing = Media::factory()->book()->create(['note' => null]);

        $this->assertSame('Recommended by Ben.', summaryFor($trimmed)->note);
        $this->assertNull(summaryFor($blank)->note);
        $this->assertNull(summaryFor($missing)->note);
    });
});

describe('history', function () {
    test('is an empty array when the item has no events', function () {
        /** @var TestCase $this */
        $media = Media::factory()->book()->create(['note' => 'Recommended by Ben.']);

        $this->assertSame([], summaryFor($media)->history);
    });

    test('includes events that carry no comment, unlike full_text', function () {
        /** @var TestCase $this */
        $media = Media::factory()->book()
            ->has(
                MediaEvent::factory()
                    ->started()
                    ->at(Carbon::create(2025, 3, 4))
                    ->state(['comment' => null]),
                'events'
            )
            ->create(['note' => null]);

        $summary = summaryFor($media);

        // assertEquals, not assertSame: jsonb sorts object keys, and key order
        // is not part of the contract. Event order still is — a list compares
        // index by index either way.
        $this->assertEquals(
            [['type' => 'started', 'occurred_at' => '2025-03-04T00:00:00+00:00', 'comment' => null]],
            $summary->history
        );
        $this->assertNull($summary->full_text);
    });

    test('lists every event oldest first with its type, timestamp, and comment', function () {
        /** @var TestCase $this */
        $media = Media::factory()->book()->create(['note' => null]);

        MediaEvent::factory()->for($media)->finished()
            ->at(Carbon::create(2025, 4, 1))->state(['comment' => 'Stuck the landing.'])->create();
        MediaEvent::factory()->for($media)->started()
            ->at(Carbon::create(2025, 3, 4))->state(['comment' => 'Slow start.'])->create();

        $this->assertEquals(
            [
                ['type' => 'started', 'occurred_at' => '2025-03-04T00:00:00+00:00', 'comment' => 'Slow start.'],
                ['type' => 'finished', 'occurred_at' => '2025-04-01T00:00:00+00:00', 'comment' => 'Stuck the landing.'],
            ],
            summaryFor($media)->history
        );
    });

    test('does not include events belonging to another media item', function () {
        /** @var TestCase $this */
        $media = Media::factory()->book()
            ->has(MediaEvent::factory()->started()->state(['comment' => 'Mine.']), 'events')
            ->create(['note' => null]);
        Media::factory()->book()
            ->has(MediaEvent::factory()->finished()->state(['comment' => 'Theirs.']), 'events')
            ->create(['note' => null]);

        $history = summaryFor($media)->history;

        $this->assertCount(1, $history);
        $this->assertSame('Mine.', $history[0]['comment']);
    });
});
