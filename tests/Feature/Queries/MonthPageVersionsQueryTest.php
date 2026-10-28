<?php

use App\Models\Memory;
use App\Models\MemoryPhoto;
use App\Models\MemoryReaction;
use App\Models\User;
use App\Queries\ListOpenMonthsQuery;
use App\Queries\MonthPageVersionsQuery;
use Database\Seeders\MemorySeeder;
use Tests\TestCase;

/**
 * The seeder counts back from the latest open month, September 2026:
 * September is before its reveal (Katie's draft, David's shared memory),
 * August has David's late share, July revealed when both shared, June is
 * empty. March is the launch month.
 *
 * Timestamps are stored to the second, so each test travels a minute
 * between changes to keep them apart.
 */
beforeEach(function () {
    /** @var TestCase $this */
    $this->travelTo('2026-10-18T12:00:00Z');
    $this->seed(MemorySeeder::class);
    $this->david = User::firstWhere('email', 'david@example.com');
    $this->katie = User::firstWhere('email', 'katie@example.com');

    $this->versionsFor = fn (User $viewer): array => (new MonthPageVersionsQuery)
        ->execute((new ListOpenMonthsQuery)->execute(), $viewer);

    $this->memoryOf = fn (User $author, string $firstDay): Memory => Memory::query()
        ->where('month_id', $firstDay)
        ->whereBelongsTo($author)
        ->sole();
});

describe('execute()', function () {
    test('gives every open month a fingerprint, empty months included', function () {
        /** @var TestCase $this */
        expect(array_keys(($this->versionsFor)($this->david)))->toBe([
            '2026-09-01', '2026-08-01', '2026-07-01', '2026-06-01', '2026-05-01', '2026-04-01', '2026-03-01',
        ]);
    });

    test('gives the same fingerprints while nothing changes', function () {
        /** @var TestCase $this */
        expect(($this->versionsFor)($this->david))->toBe(($this->versionsFor)($this->david));
    });

    test('changes only the month whose memory was edited', function () {
        /** @var TestCase $this */
        $before = ($this->versionsFor)($this->david);

        $this->travel(1)->minute();
        ($this->memoryOf)($this->katie, '2026-08-01')->update(['body' => 'Beach week, rewritten.']);
        $after = ($this->versionsFor)($this->david);

        expect($after['2026-08-01'])->not->toBe($before['2026-08-01'])
            ->and($after['2026-07-01'])->toBe($before['2026-07-01']);
    });

    test('changes when a reaction is added, and again when it is taken back', function () {
        /** @var TestCase $this */
        $before = ($this->versionsFor)($this->david);

        $this->travel(1)->minute();
        $reaction = MemoryReaction::factory()
            ->for(($this->memoryOf)($this->katie, '2026-08-01'))
            ->for($this->david)
            ->create(['emoji' => '🌊']);
        $reacted = ($this->versionsFor)($this->david);

        $this->travel(1)->minute();
        $reaction->delete();
        $takenBack = ($this->versionsFor)($this->david);

        expect($reacted['2026-08-01'])->not->toBe($before['2026-08-01'])
            ->and($takenBack['2026-08-01'])->not->toBe($reacted['2026-08-01']);
    });

    test('changes when a photo lands, and again when it is removed', function () {
        /** @var TestCase $this */
        $before = ($this->versionsFor)($this->david);

        $this->travel(1)->minute();
        $photo = MemoryPhoto::factory()->for(($this->memoryOf)($this->katie, '2026-08-01'))->ready()->create();
        $landed = ($this->versionsFor)($this->david);

        $this->travel(1)->minute();
        $photo->delete();
        $removed = ($this->versionsFor)($this->david);

        expect($landed['2026-08-01'])->not->toBe($before['2026-08-01'])
            ->and($removed['2026-08-01'])->not->toBe($landed['2026-08-01']);
    });

    test('changes when a month reveals at its deadline, though no memory changed', function () {
        /** @var TestCase $this */
        // September reveals at the end of October.
        $before = ($this->versionsFor)($this->david);

        $this->travelTo('2026-11-02T12:00:00Z');
        $after = ($this->versionsFor)($this->david);

        expect($after['2026-09-01'])->not->toBe($before['2026-09-01']);
    });

    test('says nothing about the other person working on their draft before the reveal', function () {
        /** @var TestCase $this */
        // September: Katie's draft, which David can't read yet.
        $draft = ($this->memoryOf)($this->katie, '2026-09-01');
        $davidsBefore = ($this->versionsFor)($this->david);
        $katiesBefore = ($this->versionsFor)($this->katie);

        $this->travel(1)->minute();
        $draft->update(['body' => 'Pumpkin patch, take two.']);
        MemoryPhoto::factory()->for($draft)->ready()->create();

        expect(($this->versionsFor)($this->david)['2026-09-01'])->toBe($davidsBefore['2026-09-01'])
            ->and(($this->versionsFor)($this->katie)['2026-09-01'])->not->toBe($katiesBefore['2026-09-01']);
    });

    test('changes when the other person shares their memory, which the page shows', function () {
        /** @var TestCase $this */
        $before = ($this->versionsFor)($this->david);

        $this->travel(1)->minute();
        ($this->memoryOf)($this->katie, '2026-09-01')->update(['shared_at' => now()]);

        expect(($this->versionsFor)($this->david)['2026-09-01'])->not->toBe($before['2026-09-01']);
    });
});
