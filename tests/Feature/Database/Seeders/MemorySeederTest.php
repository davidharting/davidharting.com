<?php

use App\Models\Memory;
use App\Models\MemoryParticipant;
use App\Models\MemoryPhoto;
use App\Models\MemoryReaction;
use App\Models\User;
use App\Support\CalendarMonth;
use Carbon\CarbonImmutable;
use Database\Seeders\MemorySeeder;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * @return array{david: User, katie: User, latest: CalendarMonth}
 */
function seedMemories(string $now): array
{
    CarbonImmutable::setTestNow(CarbonImmutable::parse($now));
    Storage::fake('private');
    (new MemorySeeder)->run();

    return [
        'david' => User::where('email', 'david@example.com')->sole(),
        'katie' => User::where('email', 'katie@example.com')->sole(),
        'latest' => CalendarMonth::latestOpen(),
    ];
}

function memoryOf(User $user, CalendarMonth $month): ?Memory
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

        $live = MemoryPhoto::all();

        expect($live->filter->isReady())->not->toBeEmpty()
            ->and($live->filter->isProcessing())->not->toBeEmpty()
            ->and($live->filter->isFailed())->not->toBeEmpty()
            ->and(MemoryPhoto::onlyTrashed()->count())->toBeGreaterThan(0);
    });

    test('come with and without captions', function () {
        /** @var TestCase $this */
        seedMemories('2026-10-16 12:00:00');

        expect(MemoryPhoto::whereNotNull('caption')->exists())->toBeTrue()
            ->and(MemoryPhoto::whereNull('caption')->exists())->toBeTrue();
    });

    test('fill one memory with ten photos, plus one removed', function () {
        /** @var TestCase $this */
        seedMemories('2026-10-16 12:00:00');

        $fullest = Memory::withCount('photos')->orderByDesc('photos_count')->first();

        expect($fullest->photos_count)->toBe(10)
            ->and(MemoryPhoto::withTrashed()->where('memory_id', $fullest->id)->count())->toBe(11);
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

    test('record the real size of each variant', function () {
        /** @var TestCase $this */
        seedMemories('2026-10-16 12:00:00');

        $disk = Storage::disk('private');

        MemoryPhoto::withTrashed()->get()->filter->isReady()->each(function (MemoryPhoto $photo) use ($disk) {
            foreach ([$photo->web(), $photo->thumbnail()] as $variant) {
                [$width, $height] = getimagesizefromstring($disk->get($variant->path));

                expect($variant->width)->toBe($width)
                    ->and($variant->height)->toBe($height)
                    ->and($variant->bytes)->toBe($disk->size($variant->path));
            }
        });
    });
});
