<?php

use App\Models\Memory;
use App\Models\MemoryPhoto;
use App\Models\User;
use App\Queries\ListOpenMonthsQuery;
use App\Queries\MonthPageVersionsQuery;
use App\Support\CalendarMonth;
use Database\Seeders\MemorySeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\RenderedHead;
use Tests\TestCase;

/**
 * The seeder counts back from the latest open month, September 2026:
 * September is before its reveal, August has David's late share, July
 * revealed when both shared, June is empty, and May revealed at its deadline
 * with David's draft never shared. March is the launch month.
 */
beforeEach(function () {
    /** @var TestCase $this */
    $this->travelTo('2026-10-18T12:00:00Z');
    $this->seed(MemorySeeder::class);
    $this->actingAs(User::firstWhere('email', 'david@example.com'));
});

test('lists every month since launch, newest first, empty months included', function () {
    /** @var TestCase $this */
    $this->get('/memories')
        ->assertSuccessful()
        ->assertSeeTextInOrder([
            'September 2026',
            'August 2026',
            'July 2026',
            'June 2026',
            'May 2026',
            'April 2026',
            'March 2026',
        ])
        ->assertDontSeeText('February 2026');
});

test('titles the page Memories', function () {
    /** @var TestCase $this */
    $response = $this->get('/memories');

    expect(RenderedHead::from($response)->title)->toBe('Memories - davidharting.com');
});

test('says where both memories stand before the reveal, without either memory\'s text', function () {
    /** @var TestCase $this */
    // September: Katie started her draft on Oct 3; David shared on Oct 5.
    $this->get('/memories')
        ->assertSeeText('Katie started on Oct 3 · Yours: Shared Oct 5')
        ->assertDontSeeText('pumpkin patch')
        ->assertDontSeeText('It snowed for the first time');
});

test('explains how it works behind a ? beside the title', function () {
    /** @var TestCase $this */
    $this->get('/memories')
        ->assertSee('aria-label="How it works"', false)
        ->assertSeeText('Nobody sees it until you share it.');
});

test('previews the first line of their memory once a month has revealed', function () {
    /** @var TestCase $this */
    // Katie's August memory.
    $this->get('/memories')
        ->assertSeeText('Beach week! Five days with nowhere to be.');
});

describe('months the viewer missed', function () {
    test('offer to write one, without saying nothing was written', function () {
        /** @var TestCase $this */
        // June: nobody wrote.
        $this->get('/memories')
            ->assertSee('href="'.route('memories.show', '2026-06').'?v=', false)
            ->assertSee('href="'.route('memories.write', '2026-06').'"', false)
            ->assertSeeTextInOrder(['June 2026', 'Write yours', 'May 2026'])
            ->assertDontSeeText('Nothing written.');
    });

    test('offer to keep writing a draft left behind, under the other person\'s memory', function () {
        /** @var TestCase $this */
        // May: Katie shared "New job!"; David's draft was never shared.
        $this->get('/memories')
            ->assertSee('href="'.route('memories.write', '2026-05').'"', false)
            ->assertSeeTextInOrder(['May 2026', 'New job!', 'Keep writing', 'April 2026']);
    });

    test('never say the other person didn\'t share one', function () {
        /** @var TestCase $this */
        // June from Katie's side, with a draft David never shared.
        Memory::factory()->for(User::firstWhere('email', 'david@example.com'))->forMonth(CalendarMonth::of(2026, 6))->create();

        $this->actingAs(User::firstWhere('email', 'katie@example.com'))
            ->get('/memories')
            ->assertSeeTextInOrder(['June 2026', 'Write yours', 'May 2026'])
            ->assertDontSeeText("didn't share");
    });

    test('offer to keep writing a draft left behind when there is nothing to read', function () {
        /** @var TestCase $this */
        // June, with a draft David never shared.
        Memory::factory()->for(User::firstWhere('email', 'david@example.com'))->forMonth(CalendarMonth::of(2026, 6))->create();

        $this->get('/memories')
            ->assertSee('href="'.route('memories.write', '2026-06').'"', false)
            ->assertSeeTextInOrder(['June 2026', 'Keep writing', 'May 2026']);
    });

    test('do not include the latest open month, even before the viewer shares', function () {
        /** @var TestCase $this */
        // September: Katie's draft, before the reveal.
        $this->actingAs(User::firstWhere('email', 'katie@example.com'))
            ->get('/memories')
            ->assertDontSee('href="'.route('memories.write', '2026-09').'"', false);
    });

    test('do not include months the viewer shared, on time or late', function () {
        /** @var TestCase $this */
        // August: David shared after the reveal; July and April: both shared.
        $this->get('/memories')
            ->assertDontSee('href="'.route('memories.write', '2026-08').'"', false)
            ->assertDontSee('href="'.route('memories.write', '2026-07').'"', false)
            ->assertDontSee('href="'.route('memories.write', '2026-04').'"', false);
    });
});

test('marks their unshared draft after the reveal, without its text, and previews the viewer\'s own memory instead', function () {
    /** @var TestCase $this */
    // May: David's draft was never shared; Katie's "New job!" memory was.
    // 'David: draft' is the title and aria-label of his initial, not visible text.
    $this->actingAs(User::firstWhere('email', 'katie@example.com'))
        ->get('/memories')
        ->assertSee('David: draft')
        ->assertDontSeeText('Spring cleaning')
        ->assertSeeText('New job!');
});

describe('thumbnails', function () {
    test('lead a revealed month with the other person\'s first photo, then the viewer\'s', function () {
        /** @var TestCase $this */
        // July: Katie's one photo, then the first of David's ten.
        $tomatoes = MemoryPhoto::firstWhere('caption', 'Eleven tomatoes');
        $counters = MemoryPhoto::firstWhere('caption', 'Counters!');
        $secondOfDavids = MemoryPhoto::where('memory_id', $counters->memory_id)->where('position', 1)->sole();

        $this->get('/memories')
            ->assertSeeInOrder([
                route('memories.photos.show', [$tomatoes, 'thumbnail']),
                route('memories.photos.show', [$counters, 'thumbnail']),
            ])
            ->assertDontSee(route('memories.photos.show', [$secondOfDavids, 'thumbnail']));
    });

    test("lead with each person's first photo rather than two of the other's", function () {
        /** @var TestCase $this */
        // July from Katie's side: the first of David's ten, then her one photo.
        $counters = MemoryPhoto::firstWhere('caption', 'Counters!');
        $tomatoes = MemoryPhoto::firstWhere('caption', 'Eleven tomatoes');
        $secondOfDavids = MemoryPhoto::where('memory_id', $counters->memory_id)->where('position', 1)->sole();

        $this->actingAs(User::firstWhere('email', 'katie@example.com'))
            ->get('/memories')
            ->assertSeeInOrder([
                route('memories.photos.show', [$counters, 'thumbnail']),
                route('memories.photos.show', [$tomatoes, 'thumbnail']),
            ])
            ->assertDontSee(route('memories.photos.show', [$secondOfDavids, 'thumbnail']));
    });

    test('fill both slots from one person when the other has no ready photos', function () {
        /** @var TestCase $this */
        // July from Katie's side, with her one photo removed.
        MemoryPhoto::firstWhere('caption', 'Eleven tomatoes')->delete();
        $counters = MemoryPhoto::firstWhere('caption', 'Counters!');
        $secondOfDavids = MemoryPhoto::where('memory_id', $counters->memory_id)->where('position', 1)->sole();

        $this->actingAs(User::firstWhere('email', 'katie@example.com'))
            ->get('/memories')
            ->assertSeeInOrder([
                route('memories.photos.show', [$counters, 'thumbnail']),
                route('memories.photos.show', [$secondOfDavids, 'thumbnail']),
            ]);
    });

    test('never show photos before the reveal, not even the viewer\'s own', function () {
        /** @var TestCase $this */
        // September: David shared a photo; the month hasn't revealed.
        $street = MemoryPhoto::firstWhere('caption', 'Our street before anyone was up');

        $this->get('/memories')
            ->assertDontSee(route('memories.photos.show', [$street, 'thumbnail']));
    });

    test("never show a photo from the other's draft, even after the reveal", function () {
        /** @var TestCase $this */
        // May: David's draft was never shared, so Katie sees only her own photo.
        $hallway = MemoryPhoto::firstWhere('caption', 'The hallway, finally');
        $davidsDraft = Memory::query()->where('month_id', '2026-05-01')->whereNull('shared_at')->sole();
        $draftPhoto = MemoryPhoto::factory()->for($davidsDraft)->ready()->create();

        $this->actingAs(User::firstWhere('email', 'katie@example.com'))
            ->get('/memories')
            ->assertSee(route('memories.photos.show', [$hallway, 'thumbnail']))
            ->assertDontSee(route('memories.photos.show', [$draftPhoto, 'thumbnail']));
    });
});

test('links each month to its page', function () {
    /** @var TestCase $this */
    $this->get('/memories')
        ->assertSee(route('memories.show', '2026-09'))
        ->assertSee(route('memories.show', '2026-03'));
});

test('loads Livewire and its styles, so month links can swap pages in place, sliding between them', function () {
    /** @var TestCase $this */
    $this->get('/memories')
        ->assertSee('data-update-uri', false)
        ->assertSee('--livewire-progress-bar-color', false)
        ->assertSee('wire:navigate.hover', false)
        ->assertSee('wire:transition.navigate', false);
});

test('links each month to its page fingerprinted, so a prefetched copy is never stale', function () {
    /** @var TestCase $this */
    $david = User::firstWhere('email', 'david@example.com');
    $august = (new MonthPageVersionsQuery)->execute((new ListOpenMonthsQuery)->execute(), $david)['2026-08-01'];

    $this->get('/memories')
        ->assertSee('href="'.route('memories.show', ['month' => '2026-08', 'v' => $august]).'"', false);
});

test('truncates a long first line with an ellipsis', function () {
    /** @var TestCase $this */
    Memory::factory()->for(User::firstWhere('email', 'katie@example.com'))->forMonth(CalendarMonth::of(2026, 6))->shared()->create([
        'body' => str_repeat('word ', 100),
    ]);

    // The preview stops at 160 characters, drops the trailing space, and ends in '...'.
    $this->get('/memories')
        ->assertSeeText(str_repeat('word ', 31).'word...')
        ->assertDontSeeText(str_repeat('word ', 33));
});

test('runs each query once, however many months there are', function () {
    /** @var TestCase $this */
    DB::enableQueryLog();

    $this->get('/memories')->assertSuccessful();

    $repeated = collect(DB::getQueryLog())
        ->pluck('query')
        ->reject(fn (string $query) => str_contains($query, '"sessions"'))
        ->duplicates();

    expect($repeated->all())->toBe([]);
});
