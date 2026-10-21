<?php

use App\Models\Memory;
use App\Models\MemoryParticipant;
use App\Models\MemoryPhoto;
use App\Models\MemoryReaction;
use App\Models\User;
use App\Support\Month;
use Carbon\CarbonImmutable;
use Database\Seeders\MemorySeeder;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * @return array{david: User, katie: User, latest: Month}
 */
function seedMemories(string $now): array
{
    CarbonImmutable::setTestNow(CarbonImmutable::parse($now));
    Storage::fake('private');
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

describe('photos', function () {
    test('cover each state a photo can be in', function () {
        /** @var TestCase $this */
        seedMemories('2026-10-16 12:00:00');

        $photos = MemoryPhoto::withTrashed()->get();

        expect($photos->filter->isReady())->not->toBeEmpty()
            ->and($photos->filter->isProcessing())->not->toBeEmpty()
            ->and($photos->filter->isFailed())->not->toBeEmpty()
            ->and($photos->filter->trashed())->not->toBeEmpty()
            ->and($photos->whereNotNull('caption'))->not->toBeEmpty()
            ->and($photos->whereNull('caption'))->not->toBeEmpty();
    });

    test('fill one memory with the most photos it can hold, leaving out the removed one', function () {
        /** @var TestCase $this */
        seedMemories('2026-10-16 12:00:00');

        $fullest = Memory::with('photos')->get()->max(fn (Memory $memory) => $memory->photos->count());

        expect($fullest)->toBe(10);
    });

    test('have every file they point to on the private disk', function () {
        /** @var TestCase $this */
        seedMemories('2026-10-16 12:00:00');

        MemoryPhoto::withTrashed()->get()->each(function (MemoryPhoto $photo) {
            Storage::disk('private')->assertExists($photo->original_path);

            if ($photo->isReady()) {
                Storage::disk('private')->assertExists($photo->web()->path);
                Storage::disk('private')->assertExists($photo->thumbnail()->path);
            }
        });
    });

    test('record the real size of each copy', function () {
        /** @var TestCase $this */
        seedMemories('2026-10-16 12:00:00');

        $photo = MemoryPhoto::all()->first->isReady();
        [$width, $height] = getimagesizefromstring(Storage::disk('private')->get($photo->web()->path));

        expect($photo->web()->width)->toBe($width)
            ->and($photo->web()->height)->toBe($height)
            ->and($photo->web()->bytes)->toBe(Storage::disk('private')->size($photo->web()->path));
    });
});
