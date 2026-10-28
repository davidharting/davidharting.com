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
 * September is before its reveal, August has David's late share, July
 * revealed when both shared, and June is empty.
 */
beforeEach(function () {
    /** @var TestCase $this */
    $this->travelTo('2026-10-18T12:00:00Z');
    $this->seed(MemorySeeder::class);
});

/**
 * Every open month's fingerprint, keyed by its first day.
 *
 * @return array<string, string>
 */
function monthPageVersions(): array
{
    return (new MonthPageVersionsQuery)->execute((new ListOpenMonthsQuery)->execute());
}

function augustMemoryBy(string $email): Memory
{
    return Memory::query()
        ->where('month_id', '2026-08-01')
        ->whereBelongsTo(User::firstWhere('email', $email))
        ->sole();
}

describe('execute()', function () {
    test('gives every month a fingerprint, empty months included', function () {
        expect(array_keys(monthPageVersions()))
            ->toContain('2026-09-01', '2026-08-01', '2026-06-01');
    });

    test('gives the same fingerprints while nothing changes', function () {
        expect(monthPageVersions())->toBe(monthPageVersions());
    });

    test('changes only the month whose memory was edited', function () {
        /** @var TestCase $this */
        $before = monthPageVersions();

        $this->travel(1)->minute();
        augustMemoryBy('katie@example.com')->update(['body' => 'Beach week, rewritten.']);
        $after = monthPageVersions();

        expect($after['2026-08-01'])->not->toBe($before['2026-08-01'])
            ->and($after['2026-07-01'])->toBe($before['2026-07-01']);
    });

    test('changes when a reaction is added, and again when it is taken back', function () {
        /** @var TestCase $this */
        $before = monthPageVersions();

        $this->travel(1)->minute();
        $reaction = MemoryReaction::factory()
            ->for(augustMemoryBy('katie@example.com'))
            ->for(User::firstWhere('email', 'david@example.com'))
            ->create(['emoji' => '🌊']);
        $reacted = monthPageVersions();

        $this->travel(1)->minute();
        $reaction->delete();
        $takenBack = monthPageVersions();

        expect($reacted['2026-08-01'])->not->toBe($before['2026-08-01'])
            ->and($takenBack['2026-08-01'])->not->toBeIn([$before['2026-08-01'], $reacted['2026-08-01']]);
    });

    test('changes when a photo lands, and again when it is removed', function () {
        /** @var TestCase $this */
        $before = monthPageVersions();

        $this->travel(1)->minute();
        $photo = MemoryPhoto::factory()->for(augustMemoryBy('katie@example.com'))->ready()->create();
        $landed = monthPageVersions();

        $this->travel(1)->minute();
        $photo->delete();
        $removed = monthPageVersions();

        expect($landed['2026-08-01'])->not->toBe($before['2026-08-01'])
            ->and($removed['2026-08-01'])->not->toBeIn([$before['2026-08-01'], $landed['2026-08-01']]);
    });

    test('changes when a month reveals at its deadline, though no memory changed', function () {
        /** @var TestCase $this */
        // September reveals at the end of October.
        $before = monthPageVersions();

        $this->travelTo('2026-11-02T12:00:00Z');
        $after = monthPageVersions();

        expect($after['2026-09-01'])->not->toBe($before['2026-09-01']);
    });
});
