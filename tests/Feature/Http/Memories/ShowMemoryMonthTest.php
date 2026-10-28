<?php

use App\Livewire\Memories\Reactions;
use App\Models\Memory;
use App\Models\MemoryPhoto;
use App\Models\User;
use App\Support\CalendarMonth;
use Database\Seeders\MemorySeeder;
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
    $this->david = User::firstWhere('email', 'david@example.com');
    $this->katie = User::firstWhere('email', 'katie@example.com');
});

describe('before the reveal', function () {
    test('shows where their memory stands but not their text', function () {
        /** @var TestCase $this */
        // Nov 1 is September's reveal deadline.
        $this->actingAs($this->david)->get('/memories/2026-09')
            ->assertSuccessful()
            ->assertSeeText('Katie started on Oct 3')
            ->assertSeeText('Reveals when Katie shares, or on Nov 1 at the latest.')
            ->assertDontSeeText('pumpkin patch');
    });

    test('loads Livewire and its styles even with no reactions on the page, so its links can swap pages in place', function () {
        /** @var TestCase $this */
        $this->actingAs($this->david)->get('/memories/2026-09')
            ->assertSee('data-update-uri', false)
            ->assertSee('--livewire-progress-bar-color', false)
            ->assertSee('wire:navigate.hover', false);
    });

    test('prefetches the other tab, so switching to it is instant', function () {
        /** @var TestCase $this */
        $yoursTab = route('memories.show', ['month' => '2026-09', 'who' => 'yours']);

        expect($this->actingAs($this->david)->get('/memories/2026-09')->getContent())
            ->toMatch('#href="'.preg_quote($yoursTab, '#').'"\s+data-prefetch-in-view#');
    });

    test('titles the page with the month', function () {
        /** @var TestCase $this */
        $response = $this->actingAs($this->david)->get('/memories/2026-09');

        expect(RenderedHead::from($response)->title)->toBe('September 2026 - davidharting.com');
    });

    test('asks the viewer to share once the other person has shared', function () {
        /** @var TestCase $this */
        $this->actingAs($this->katie)->get('/memories/2026-09')
            ->assertSeeText('David shared on Oct 5')
            ->assertSeeText('Share yours to reveal both.')
            ->assertDontSeeText('It snowed for the first time');
    });

    test('has no reaction row on either tab before the month reveals', function () {
        /** @var TestCase $this */
        $this->actingAs($this->david)->get('/memories/2026-09')
            ->assertDontSeeLivewire(Reactions::class);

        $this->actingAs($this->david)->get('/memories/2026-09?who=yours')
            ->assertDontSeeLivewire(Reactions::class);
    });

    test('shows the viewer their own draft', function () {
        /** @var TestCase $this */
        $this->actingAs($this->katie)->get('/memories/2026-09?who=yours')
            ->assertSeeTextInOrder(['Draft, started Oct 3', 'Keep writing', 'The pumpkin patch, and how long it took to pick one.'])
            ->assertSee(route('memories.write', '2026-09'));
    });
});

describe('after the reveal', function () {
    test('shows their memory', function () {
        /** @var TestCase $this */
        $this->actingAs($this->david)->get('/memories/2026-07')
            ->assertSeeText('The garden finally did something.');
    });

    test('doesn\'t say when the month revealed or when either memory was shared', function () {
        /** @var TestCase $this */
        // Katie's share on Aug 16 revealed July.
        $this->actingAs($this->david)->get('/memories/2026-07')
            ->assertDontSeeText('Revealed')
            ->assertDontSeeText('Shared');

        $this->actingAs($this->david)->get('/memories/2026-07?who=yours')
            ->assertDontSeeText('Revealed')
            ->assertDontSeeText('Shared');
    });

    test('puts the reaction row between the text and the photos', function () {
        /** @var TestCase $this */
        $this->actingAs($this->katie)->get('/memories/2026-08')
            ->assertSeeLivewire(Reactions::class)
            ->assertSeeInOrder(['Late again, sorry.', 'Remove your 😂 reaction', 'Add a reaction', 'Didn’t know half the songs'], false);
    });

    test('shows a share that came after a deadline reveal like any other', function () {
        /** @var TestCase $this */
        // Oct 1 is August's deadline; David shared late, on Oct 4. There is no late label.
        $this->actingAs($this->david)->get('/memories/2026-08?who=yours')
            ->assertDontSeeText('after the reveal')
            ->assertSeeText('Late again, sorry.');

        $this->actingAs($this->katie)->get('/memories/2026-08')
            ->assertSeeText('Late again, sorry.');
    });

    test('never shows a draft that was not shared by the deadline', function () {
        /** @var TestCase $this */
        // May revealed at its deadline.
        $this->actingAs($this->katie)->get('/memories/2026-05')
            ->assertSeeText('David didn\'t share a memory this month.')
            ->assertDontSeeText('Spring cleaning');
    });

    test('shows an empty month', function () {
        /** @var TestCase $this */
        $this->actingAs($this->david)->get('/memories/2026-06')
            ->assertSeeText('Katie didn\'t share a memory this month.');

        $this->actingAs($this->david)->get('/memories/2026-06?who=yours')
            ->assertSeeText('You haven\'t written about this month yet.')
            ->assertSeeText('Write yours')
            ->assertSee(route('memories.write', '2026-06'));
    });
});

test('links to both memories\' tabs', function () {
    /** @var TestCase $this */
    $this->actingAs($this->david)->get('/memories/2026-07')
        ->assertSee(route('memories.show', ['month' => '2026-07', 'who' => 'yours']))
        ->assertSeeTextInOrder(['Katie', 'Yours']);
});

test('links to the neighbouring months next to All months, above the title', function () {
    /** @var TestCase $this */
    $this->actingAs($this->david)->get('/memories/2026-07')
        ->assertSee(route('memories.show', '2026-06'))
        ->assertSee(route('memories.show', '2026-08'))
        ->assertSeeTextInOrder(['← All months', '← June', 'August →', 'July 2026'])
        ->assertDontSeeText('June 2026')
        ->assertSee('aria-label="June 2026"', false);
});

test('doesn\'t link past the latest open month or before launch', function () {
    /** @var TestCase $this */
    $this->actingAs($this->david)->get('/memories/2026-09')
        ->assertSee(route('memories.show', '2026-08'))
        ->assertDontSee(route('memories.show', '2026-10'));

    $this->actingAs($this->david)->get('/memories/2026-03')
        ->assertSee(route('memories.show', '2026-04'))
        ->assertDontSee(route('memories.show', '2026-02'));
});

test('keeps paragraphs and escapes the text', function () {
    /** @var TestCase $this */
    Memory::factory()->for($this->david)->forMonth(CalendarMonth::of(2026, 6))->create([
        'body' => "First paragraph\nsame paragraph\n\n<b>Second</b>",
    ]);

    // Line breaks are kept as written and rendered with pre-line whitespace.
    $this->actingAs($this->david)->get('/memories/2026-06?who=yours')
        ->assertSee("First paragraph\nsame paragraph\n\n&lt;b&gt;Second&lt;/b&gt;", false)
        ->assertDontSee('<b>Second</b>', false);
});

test('is not found for a month outside the list or a malformed month', function (string $month) {
    /** @var TestCase $this */
    $this->actingAs($this->david)->get("/memories/{$month}")->assertNotFound();
})->with([
    'not open yet' => '2026-10',
    'before launch' => '2026-02',
    'not a month' => '2026-13',
    'not a date' => 'september',
]);

describe('ways into the editor', function () {
    test('offers an Edit link, not Keep writing or a shared date, above your shared memory', function () {
        /** @var TestCase $this */
        // David shared September on Oct 5.
        $this->actingAs($this->david)->get('/memories/2026-09?who=yours')
            ->assertSeeText('Edit')
            ->assertDontSeeText('Shared Oct 5')
            ->assertDontSeeText('Keep writing')
            ->assertSee(route('memories.write', '2026-09'));
    });

    test('links to your editor from their page while yours is unshared', function () {
        /** @var TestCase $this */
        $this->actingAs($this->katie)->get('/memories/2026-09')
            ->assertSeeText('Keep writing')
            ->assertSee(route('memories.write', '2026-09'));
    });

    test('doesn\'t link to your editor from their page once you\'ve shared', function () {
        /** @var TestCase $this */
        $this->actingAs($this->david)->get('/memories/2026-09')
            ->assertDontSee(route('memories.write', '2026-09'));
    });

    test('doesn\'t link to your editor from their page after the reveal, even with a draft', function () {
        /** @var TestCase $this */
        // May revealed at its deadline with David's draft never shared.
        $this->actingAs($this->david)->get('/memories/2026-05')
            ->assertDontSee(route('memories.write', '2026-05'));
    });
});

describe('photos', function () {
    test('shows their photos with captions in order after the reveal', function () {
        /** @var TestCase $this */
        // July: David's memory holds the full ten photos.
        $counters = MemoryPhoto::firstWhere('caption', 'Counters!');
        $gate = MemoryPhoto::firstWhere('caption', 'The gate, still broken');

        $this->actingAs($this->katie)->get('/memories/2026-07')
            ->assertSeeInOrder([route('memories.photos.show', [$counters, 'web']), route('memories.photos.show', [$gate, 'web'])])
            ->assertSeeTextInOrder(['Counters!', 'Hometown, unchanged', 'The gate, still broken']);
    });

    test('uses the caption as alt text, or who took the photo when there is none', function () {
        /** @var TestCase $this */
        // July: two of David's ten photos have no caption.
        $this->actingAs($this->katie)->get('/memories/2026-07')
            ->assertSee('alt="A photo by David"', false)
            ->assertSee('alt="Counters!"', false);
    });

    test('never shows a removed photo', function () {
        /** @var TestCase $this */
        $removed = MemoryPhoto::onlyTrashed()->firstWhere('caption', 'Taken off again');

        $this->actingAs($this->katie)->get('/memories/2026-07')
            ->assertDontSeeText('Taken off again')
            ->assertDontSee(route('memories.photos.show', [$removed, 'web']));

        $this->actingAs($this->david)->get('/memories/2026-07?who=yours')
            ->assertDontSeeText('Taken off again');
    });

    test('hides their photos before the reveal', function () {
        /** @var TestCase $this */
        // September: David shared, Katie hasn't.
        $street = MemoryPhoto::firstWhere('caption', 'Our street before anyone was up');

        $this->actingAs($this->katie)->get('/memories/2026-09')
            ->assertDontSeeText('Our street before anyone was up')
            ->assertDontSee(route('memories.photos.show', [$street, 'web']));
    });

    test('shows the author a placeholder for a photo still processing', function () {
        /** @var TestCase $this */
        $this->actingAs($this->david)->get('/memories/2026-09?who=yours')
            ->assertSeeTextInOrder(['Our street before anyone was up', 'This photo is still processing.']);
    });

    test('shows the author a placeholder for a photo that failed', function () {
        /** @var TestCase $this */
        $this->actingAs($this->katie)->get('/memories/2026-09?who=yours')
            ->assertSeeTextInOrder(['This photo couldn\'t be processed.', 'The pumpkin patch']);
    });

    test('never shows the other person a photo that isn\'t ready', function () {
        /** @var TestCase $this */
        // August has revealed; Katie's memory gets one photo still processing and one failed.
        $katies = $this->katie->memories()->where('month_id', '2026-08-01')->sole();
        MemoryPhoto::factory()->for($katies)->captioned('Still on its way')->create(['position' => 5]);
        MemoryPhoto::factory()->for($katies)->captioned('Never made it')->failed()->create(['position' => 6]);

        $this->actingAs($this->david)->get('/memories/2026-08')
            ->assertSeeText('Every night from the porch')
            ->assertDontSeeText('Still on its way')
            ->assertDontSeeText('Never made it')
            ->assertDontSeeText('This photo is still processing.')
            ->assertDontSeeText("This photo couldn't be processed.");
    });
});
