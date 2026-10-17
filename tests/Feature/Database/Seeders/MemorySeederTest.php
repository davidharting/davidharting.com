<?php

use App\Models\Memory;
use App\Models\MemoryParticipant;
use App\Models\MemoryReaction;
use App\Models\User;
use App\Support\Month;
use Carbon\CarbonImmutable;
use Database\Seeders\MemorySeeder;
use Tests\TestCase;

/**
 * @return array{david: User, katie: User, latest: Month}
 */
function seedMemories(string $now): array
{
    CarbonImmutable::setTestNow(CarbonImmutable::parse($now));
    (new MemorySeeder)->run();

    return [
        'david' => User::where('email', 'david@example.com')->sole(),
        'katie' => User::where('email', 'katie@example.com')->sole(),
        'latest' => Month::latestOpen(),
    ];
}

function memoryOf(User $user, Month $month): ?Memory
{
    return Memory::where('user_id', $user->id)->where('month', $month->toDateString())->first();
}

afterEach(function () {
    CarbonImmutable::setTestNow();
});

test('creates two participants with display names', function () {
    /** @var TestCase $this */
    ['david' => $david, 'katie' => $katie] = seedMemories('2026-10-16 12:00:00');

    expect(MemoryParticipant::count())->toBe(2)
        ->and($david->displayName())->toBe('David')
        ->and($katie->displayName())->toBe('Katie')
        ->and($david->is_admin)->toBeTrue()
        ->and($katie->is_admin)->toBeFalse();
});

test('seeds each state the design sessions need', function () {
    /** @var TestCase $this */
    ['david' => $david, 'katie' => $katie, 'latest' => $latest] = seedMemories('2026-10-16 12:00:00');

    // The latest open month: David shared, Katie is drafting.
    expect(memoryOf($david, $latest)->isShared())->toBeTrue()
        ->and(memoryOf($katie, $latest)->isDraft())->toBeTrue();

    // A late share: David shared after the month's fallback reveal.
    $lateMonth = $latest->previous(1);
    expect(memoryOf($david, $lateMonth)->shared_at->greaterThan($lateMonth->revealDeadline()))->toBeTrue()
        ->and(memoryOf($katie, $lateMonth)->shared_at->lessThan($lateMonth->revealDeadline()))->toBeTrue();

    // An empty month.
    expect(Memory::where('month', $latest->previous(3)->toDateString())->exists())->toBeFalse();

    // Revealed at the deadline with a draft left behind.
    expect(memoryOf($katie, $latest->previous(4))->isShared())->toBeTrue()
        ->and(memoryOf($david, $latest->previous(4))->isDraft())->toBeTrue();
});

test('only reacts to shared memories, never their own', function () {
    /** @var TestCase $this */
    seedMemories('2026-10-16 12:00:00');

    $reactions = MemoryReaction::with('memory')->get();

    expect($reactions)->not->toBeEmpty();
    $reactions->each(function ($reaction) {
        expect($reaction->memory->isShared())->toBeTrue()
            ->and($reaction->user_id)->not->toBe($reaction->memory->user_id);
    });
});

test('never writes a time in the future, even on the day a month opens', function () {
    /** @var TestCase $this */
    seedMemories('2026-10-01 04:30:00');
    $now = CarbonImmutable::now();

    expect(Memory::where('created_at', '>', $now)->orWhere('shared_at', '>', $now)->exists())->toBeFalse()
        ->and(MemoryReaction::where('created_at', '>', $now)->exists())->toBeFalse();
});
