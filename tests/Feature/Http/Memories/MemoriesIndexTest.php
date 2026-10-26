<?php

use App\Models\Memory;
use App\Models\MemoryPhoto;
use App\Models\User;
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

test('previews the first line of their memory once a month has revealed', function () {
    /** @var TestCase $this */
    // Katie's August memory.
    $this->get('/memories')
        ->assertSeeText('Beach week! Five days with nowhere to be.');
});

test('says nothing was written for an empty month', function () {
    /** @var TestCase $this */
    $this->get('/memories')
        ->assertSeeText('Nothing written.');
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
