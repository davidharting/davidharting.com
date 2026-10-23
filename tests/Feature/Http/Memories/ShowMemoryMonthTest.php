<?php

use App\Models\Memory;
use App\Models\User;
use App\Support\Month;
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
        // Aug 16 is Katie's share, the second one.
        $this->actingAs($this->david)->get('/memories/2026-07')
            ->assertSeeText('Revealed Aug 16.')
            ->assertSeeText('The garden finally did something.');
    });

    test('shows a share that came after a deadline reveal like any other', function () {
        /** @var TestCase $this */
        // Oct 1 is August's deadline; David shared late, on Oct 4. There is no late label.
        $this->actingAs($this->david)->get('/memories/2026-08?who=yours')
            ->assertSeeText('Revealed Oct 1.')
            ->assertSeeText('Shared Oct 4')
            ->assertDontSeeText('after the reveal')
            ->assertSeeText('Late again, sorry.');

        $this->actingAs($this->katie)->get('/memories/2026-08')
            ->assertSeeText('Late again, sorry.');
    });

    test('never shows a draft that was not shared by the deadline', function () {
        /** @var TestCase $this */
        // Jul 1 is May's deadline.
        $this->actingAs($this->katie)->get('/memories/2026-05')
            ->assertSeeText('Revealed Jul 1.')
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

test('links to both memories\' tabs and the neighbouring months', function () {
    /** @var TestCase $this */
    $this->actingAs($this->david)->get('/memories/2026-07')
        ->assertSee(route('memories.show', ['month' => '2026-07', 'who' => 'yours']))
        ->assertSee(route('memories.show', '2026-06'))
        ->assertSee(route('memories.show', '2026-08'))
        ->assertSeeTextInOrder(['Katie', 'Yours'])
        ->assertSeeTextInOrder(['← June 2026', 'August 2026 →']);
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
    Memory::factory()->for($this->david)->forMonth(Month::of(2026, 6))->create([
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
    test('offers an Edit link, not Keep writing, next to your shared memory', function () {
        /** @var TestCase $this */
        $this->actingAs($this->david)->get('/memories/2026-09?who=yours')
            ->assertSeeTextInOrder(['Shared Oct 5', 'Edit'])
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
