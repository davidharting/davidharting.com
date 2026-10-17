<?php

namespace Database\Seeders;

use App\Models\Memory;
use App\Models\MemoryParticipant;
use App\Models\MemoryReaction;
use App\Models\User;
use App\Support\Month;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Two participants and seven months of memories, ending with the latest open
 * month, covering each state a month and a memory can be in.
 *
 * Months are counted back from the latest open month, so the states hold
 * whatever day this runs:
 *
 * - 0 months back: open, before its reveal. David has shared; Katie has a draft.
 * - 1 back: Katie shared on time, David shared after the fallback reveal (a late share).
 * - 2 back: both shared, revealed when the second one shared.
 * - 3 back: an empty month; nobody wrote.
 * - 4 back: Katie shared; David's draft was never shared, so the month revealed at its deadline.
 * - 5 and 6 back: both shared and revealed. 6 back is the launch month.
 */
class MemorySeeder extends Seeder
{
    private CarbonImmutable $now;

    public function run(): void
    {
        $this->now = CarbonImmutable::now();

        $david = $this->participant('David Harting', 'David', 'david@example.com', isAdmin: true);
        $katie = $this->participant('Katie Harting', 'Katie', 'katie@example.com', isAdmin: false);

        $latest = Month::latestOpen();

        // 0 back: open and not yet revealed.
        $month = $latest;
        $this->memory($david, $month, self::BODIES['first_snow'], startedAfter: 1, sharedAfter: 3);
        $this->memory($katie, $month, self::BODIES['draft_in_progress'], startedAfter: 2);

        // 1 back: a late share after the fallback reveal.
        $month = $latest->previous(1);
        $katies = $this->memory($katie, $month, self::BODIES['beach_week'], startedAfter: 4, sharedAfter: 9);
        $davids = $this->memory($david, $month, self::BODIES['late_and_sorry'], startedAfter: 20, sharedAt: $month->revealDeadline()->addDays(3)->setTime(22, 10));
        $this->react($david, $katies, ['❤️', '🥹']);
        $this->react($katie, $davids, ['😂']);

        // 2 back: both shared, revealed at the second share.
        $month = $latest->previous(2);
        $davids = $this->memory($david, $month, self::BODIES['long_month'], startedAfter: 1, sharedAfter: 6);
        $katies = $this->memory($katie, $month, self::BODIES['garden'], startedAfter: 3, sharedAfter: 12);
        $this->react($katie, $davids, ['❤️', '😭', '👏', '🙌', '🎉']);
        $this->react($david, $katies, ['😍', '🌱']);

        // 3 back: nobody wrote.

        // 4 back: Katie shared, David's draft stayed a draft, revealed at the deadline.
        $month = $latest->previous(4);
        $katies = $this->memory($katie, $month, self::BODIES['new_job'], startedAfter: 2, sharedAfter: 5);
        $this->memory($david, $month, self::BODIES['abandoned_draft'], startedAfter: 10);
        $this->react($david, $katies, ['🎉', '👏']);

        // 5 back: both shared.
        $month = $latest->previous(5);
        $davids = $this->memory($david, $month, self::BODIES['short'], startedAfter: 3, sharedAfter: 3);
        $katies = $this->memory($katie, $month, self::BODIES['road_trip'], startedAfter: 1, sharedAfter: 4);
        $this->react($katie, $davids, ['😂']);
        $this->react($david, $katies, ['❤️', '🚗']);

        // 6 back: the launch month, both shared.
        $month = $latest->previous(6);
        $davids = $this->memory($david, $month, self::BODIES['first_one'], startedAfter: 1, sharedAfter: 2);
        $katies = $this->memory($katie, $month, self::BODIES['first_one_katie'], startedAfter: 1, sharedAfter: 2);
        $this->react($katie, $davids, ['🥹']);
        $this->react($david, $katies, ['❤️']);
    }

    private function participant(string $name, string $displayName, string $email, bool $isAdmin): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'display_name' => $displayName,
            'email' => $email,
            'is_admin' => $isAdmin,
        ]);

        MemoryParticipant::create(['user_id' => $user->id]);

        return $user;
    }

    /**
     * A memory started and (optionally) shared some days after its month opened,
     * never later than now.
     */
    private function memory(
        User $author,
        Month $month,
        string $body,
        int $startedAfter,
        ?int $sharedAfter = null,
        ?CarbonImmutable $sharedAt = null,
    ): Memory {
        $createdAt = $this->notAfterNow($month->opensAt()->addDays($startedAfter)->setTime(20, 15));

        if ($sharedAfter !== null) {
            $sharedAt = $createdAt->addDays($sharedAfter)->setTime(21, 40);
        }

        $sharedAt = $sharedAt ? $this->notAfterNow($sharedAt->max($createdAt)) : null;

        return Memory::factory()->for($author)->forMonth($month)->create([
            'body' => $body,
            'created_at' => $createdAt,
            'updated_at' => $sharedAt ?? $createdAt,
            'shared_at' => $sharedAt,
        ]);
    }

    /**
     * @param  array<int, string>  $emoji
     */
    private function react(User $reactor, Memory $memory, array $emoji): void
    {
        foreach ($emoji as $index => $character) {
            MemoryReaction::create([
                'memory_id' => $memory->id,
                'user_id' => $reactor->id,
                'emoji' => $character,
                'created_at' => $this->notAfterNow($memory->shared_at->toImmutable()->addHours(2 + $index)),
            ]);
        }
    }

    private function notAfterNow(CarbonImmutable $at): CarbonImmutable
    {
        return $at->min($this->now);
    }

    /**
     * Sample memory bodies. All of them were written by an LLM; none are real.
     *
     * @var array<string, string>
     */
    private const BODIES = [
        'first_snow' => <<<'TEXT'
It snowed for the first time this year, just enough to cover the car. We went out before breakfast and the whole street was quiet.

Work was a lot this month. I shipped the thing I'd been stuck on since summer, and it felt less like a triumph and more like putting down a heavy bag.

Best meal: soup at the little place by the library, on the coldest night.
TEXT,
        'draft_in_progress' => <<<'TEXT'
Things I want to remember:

The pumpkin patch, and how long it took to pick one.
TEXT,
        'beach_week' => <<<'TEXT'
Beach week! Five days with nowhere to be. I read two whole books, which hasn't happened since before the move.

The rental had a porch that faced the water and every night we sat out there until the bugs chased us in. On the last night there was a thunderstorm out over the ocean, lightning with no sound, and we watched it for an hour.

Coming home was hard. The laundry pile is still here.
TEXT,
        'late_and_sorry' => <<<'TEXT'
Late again, sorry. This month went by in a blur.

The highlight was the concert. I didn't know half the songs and it didn't matter.
TEXT,
        'long_month' => <<<'TEXT'
This was a long one, so bear with me.

We started the month with the kitchen torn apart. For two weeks we cooked on a hot plate in the dining room and ate a frankly unreasonable amount of toast. The day the counters went in, we stood there touching them like they were a museum exhibit.

Mid-month I went back to my hometown for the reunion. Most people looked exactly the same and also completely different. I talked to my old lab partner for an hour about nothing and it was the best conversation I had all month.

Then the heat wave. Three days over a hundred, the air conditioner gave up on the second one, and we slept in the basement on the camping pads. Weirdly one of my favorite memories of the whole summer.

Things I'm grateful for: the new counters, the basement, iced coffee, you.

Things I want to do next month: actually finish a book. Call my brother. Fix the gate.
TEXT,
        'garden' => <<<'TEXT'
The garden finally did something. Eleven tomatoes, one zucchini the size of my forearm, and basil everywhere.

I learned how to make pesto properly, which mostly means using a lot more cheese than I thought.
TEXT,
        'new_job' => <<<'TEXT'
New job! First week was all laptops and passwords and trying to remember twenty new names.

By the end of the month it started to feel normal. I have a lunch spot now. I have a favorite coworker (don't tell the others).

Also: we finally hung the pictures in the hallway.
TEXT,
        'abandoned_draft' => <<<'TEXT'
Started this and never got back to it.

Spring cleaning, the
TEXT,
        'short' => 'Mostly I remember being tired, and the dog learning to open the back door.',
        'road_trip' => <<<'TEXT'
Road trip to see my parents. Nine hours there, eleven back because of the detour.

We listened to an entire audiobook and argued about the ending for the last hundred miles. You were wrong, for the record.

Mom made the lemon cake.
TEXT,
        'first_one' => <<<'TEXT'
The first one of these. I'm not sure yet what it's supposed to be, so here's what stuck with me.

The ice storm that knocked the power out for a day and a half. Playing cards by lamplight. The neighbors bringing over their generator cord.
TEXT,
        'first_one_katie' => <<<'TEXT'
I like this idea. Here goes.

I started running again, very slowly. My knees have opinions.

We went to the new bakery three Saturdays in a row and I regret nothing.
TEXT,
    ];
}
