<?php

use App\Livewire\Memories\Editor;
use App\Memories\MemoryWriter;
use App\Models\Memory;
use App\Models\User;
use App\Support\Month;
use Database\Seeders\MemorySeeder;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The seeder counts back from the latest open month, September 2026, as of
 * Oct 18. October isn't open yet.
 *
 * - September: David shared his ("It snowed for the first time…") on Oct 5.
 *   Katie has a draft ("Things I want to remember: The pumpkin patch…")
 *   started on Oct 3. It reveals when Katie shares, or on Nov 1.
 * - July: both shared, so it has revealed.
 * - June: nobody wrote, and it revealed empty at its Aug 1 deadline.
 *
 * Some copy has apostrophes written straight into the template. Blade only
 * escapes echoed values, so those assertions pass `false` to match them
 * unescaped.
 */
beforeEach(function () {
    /** @var TestCase $this */
    $this->travelTo('2026-10-18T12:00:00Z');
    $this->seed(MemorySeeder::class);
    $this->david = User::firstWhere('email', 'david@example.com');
    $this->katie = User::firstWhere('email', 'katie@example.com');
});

function editorFor(User $viewer, string $month): Testable
{
    return Livewire::actingAs($viewer)->test(Editor::class, ['month' => $month]);
}

function storedMemoryOf(User $user, string $month): ?Memory
{
    return Memory::query()->where('user_id', $user->id)->where('month', "{$month}-01")->first();
}

/**
 * Saves the memory the way an editor open on another device would.
 */
function saveOnAnotherDevice(User $author, string $month, string $body): void
{
    $writer = new MemoryWriter($author, Month::parseYearMonth($month));
    $writer->save($body, storedMemoryOf($author, $month)?->lock_version);
}

describe('writing', function () {
    test('opens with the viewer\'s draft', function () {
        /** @var TestCase $this */
        editorFor($this->katie, '2026-09')
            ->assertSet('body', fn (string $body) => str_starts_with($body, "Things I want to remember:\n\nThe pumpkin patch"))
            ->assertSeeText('Saved');
    });

    test('saves as the viewer types, without sharing', function () {
        /** @var TestCase $this */
        editorFor($this->katie, '2026-09')->set('body', 'We finally fixed the gate.');

        expect(storedMemoryOf($this->katie, '2026-09'))
            ->body->toBe('We finally fixed the gate.')
            ->isDraft()->toBeTrue();
    });

    test('starts a memory for an empty month on the first save', function () {
        /** @var TestCase $this */
        editorFor($this->david, '2026-06')
            ->assertSet('body', '')
            ->assertDontSeeText('Saved')
            ->set('body', 'June, at last.')
            ->assertSeeText('Saved');

        expect(storedMemoryOf($this->david, '2026-06')->body)->toBe('June, at last.');
    });

    test('keeps the version it saved, so the next save isn\'t seen as a conflict', function () {
        /** @var TestCase $this */
        editorFor($this->david, '2026-06')
            ->set('body', 'One')
            ->set('body', 'One, two')
            ->set('body', 'One, two, three')
            ->assertSet('changedElsewhere', false);

        expect(storedMemoryOf($this->david, '2026-06')->body)->toBe('One, two, three');
    });

    test('says where the month stands, above the text', function (string $viewer, string $month, string $line) {
        /** @var TestCase $this */
        editorFor($this->{$viewer}, $month)->assertSeeText($line);
    })->with([
        'the other person has shared' => ['katie', '2026-09', 'David has shared. Share yours to reveal both.'],
        'revealed before the viewer shared' => ['david', '2026-06', 'June has revealed, so Katie can read yours as soon as you share it.'],
        'the viewer shared before the reveal' => ['david', '2026-09', 'Shared Oct 5. Edits still save as you type, and Katie sees them once September is revealed.'],
        'the viewer shared and it revealed' => ['david', '2026-07', 'Edits still save as you type, and Katie sees them.'],
    ]);

    test('is not found for a month that isn\'t open', function () {
        /** @var TestCase $this */
        editorFor($this->david, '2026-10')->assertNotFound();
    });

    test('is not found for someone who isn\'t a participant', function () {
        /** @var TestCase $this */
        editorFor(User::factory()->create(), '2026-09')->assertNotFound();
    });
});

describe('June, before its Aug 1 deadline', function () {
    beforeEach(function () {
        /** @var TestCase $this */
        $this->travelTo('2026-07-10T12:00:00Z');
    });

    test('says the other person hasn\'t started', function () {
        /** @var TestCase $this */
        editorFor($this->david, '2026-06')->assertSeeText('Katie hasn\'t started yet.');
    });

    test('says the other person started but hasn\'t shared', function () {
        /** @var TestCase $this */
        saveOnAnotherDevice($this->katie, '2026-06', 'A start on June.');

        editorFor($this->david, '2026-06')->assertSeeText('Katie started on Jul 10 and hasn\'t shared yet.');
    });

    test('says in the Share sheet that the other person can read it once both have shared', function () {
        /** @var TestCase $this */
        editorFor($this->david, '2026-06')->assertSeeText('Katie can read it once you\'ve both shared, or on Aug 1.');
    });

    test('waits for the other person when sharing first', function () {
        /** @var TestCase $this */
        editorFor($this->david, '2026-06')
            ->set('body', 'June, at last.')
            ->call('share')
            ->assertSeeText('Shared.')
            ->assertSeeText('Reveals when Katie shares, or on Aug 1 at the latest.')
            ->assertSeeText('Back to June');
    });
});

describe('another device', function () {
    beforeEach(function () {
        /** @var TestCase $this */
        $this->editor = editorFor($this->katie, '2026-09');
        saveOnAnotherDevice($this->katie, '2026-09', 'From the phone');
    });

    test('stops saving when the memory changed elsewhere', function () {
        /** @var TestCase $this */
        $this->editor->set('body', 'From the laptop')
            ->assertSet('changedElsewhere', true)
            ->assertSeeText('Changed on another device')
            ->assertSeeText('Use that one')
            ->assertSeeText('Keep this one')
            ->set('body', 'Still typing on the laptop');

        expect(storedMemoryOf($this->katie, '2026-09')->body)->toBe('From the phone');
    });

    test('can take the other device\'s version', function () {
        /** @var TestCase $this */
        $this->editor->set('body', 'From the laptop')
            ->call('useTheirs')
            ->assertSet('body', 'From the phone')
            ->assertSet('changedElsewhere', false)
            ->set('body', 'From the phone, then the laptop');

        expect(storedMemoryOf($this->katie, '2026-09')->body)->toBe('From the phone, then the laptop');
    });

    test('can keep this editor\'s version', function () {
        /** @var TestCase $this */
        $this->editor->set('body', 'From the laptop')
            ->call('keepMine')
            ->assertSet('changedElsewhere', false)
            ->assertSeeText('Saved');

        expect(storedMemoryOf($this->katie, '2026-09')->body)->toBe('From the laptop');
    });

    test('ignores "Keep this one" when no conflict has been detected', function () {
        /** @var TestCase $this */
        $this->editor->call('keepMine');

        expect(storedMemoryOf($this->katie, '2026-09')->body)->toBe('From the phone');
    });
});

describe('sharing', function () {
    test('shows the Share button and sheet, saying September reveals as soon as you share', function () {
        /** @var TestCase $this */
        editorFor($this->katie, '2026-09')
            ->assertSeeText('Share with David…')
            ->assertSeeText('Only you can see this until you share it.')
            ->assertSeeText('David has shared, so September reveals as soon as you do.')
            ->assertSeeText('You can keep editing it afterwards.')
            ->assertSeeText('You can\'t unshare it.', false);
    });

    test('reveals the month when the second person shares', function () {
        /** @var TestCase $this */
        editorFor($this->katie, '2026-09')
            ->set('body', 'The pumpkin patch, finished.')
            ->call('share')
            ->assertSeeText('September is revealed.')
            ->assertSeeText('Read David\'s →', false)
            ->assertSeeText('Shared · Saved');

        expect(storedMemoryOf($this->katie, '2026-09'))
            ->body->toBe('The pumpkin patch, finished.')
            ->isShared()->toBeTrue();
    });

    test('after a deadline reveal, says they can read it right away and shows the month as revealed', function () {
        /** @var TestCase $this */
        // Katie has no June memory, so there's nothing of hers to read and the button goes back to June.
        editorFor($this->david, '2026-06')
            ->assertSeeText('June has already revealed, so Katie can read it right away.')
            ->set('body', 'Better late.')
            ->call('share')
            ->assertSeeText('June is revealed.')
            ->assertSeeText('Back to June');
    });

    test('keeps saving edits after sharing, without offering to share again', function () {
        /** @var TestCase $this */
        editorFor($this->david, '2026-09')
            ->assertDontSeeText('Share with Katie…')
            ->assertSeeText('Shared · Saved')
            ->set('body', 'An edit after sharing.');

        expect(storedMemoryOf($this->david, '2026-09'))
            ->body->toBe('An edit after sharing.')
            ->isShared()->toBeTrue();
    });

    test('won\'t empty a shared memory', function () {
        /** @var TestCase $this */
        editorFor($this->david, '2026-09')
            ->set('body', '')
            ->assertHasErrors('body')
            ->assertSeeText('A shared memory can\'t be empty.');

        expect(storedMemoryOf($this->david, '2026-09')->body)->toStartWith('It snowed for the first time');
    });

    test('won\'t share nothing', function () {
        /** @var TestCase $this */
        editorFor($this->david, '2026-06')
            ->call('share')
            ->assertHasErrors('body')
            ->assertSeeText('There\'s nothing to share yet.')
            ->assertDontSeeText('June is revealed.');

        expect(storedMemoryOf($this->david, '2026-06'))->toBeNull();
    });
});

describe('what the browser can change', function () {
    test('refuses changes to locked state', function (string $property, mixed $value) {
        /** @var TestCase $this */
        editorFor($this->katie, '2026-09')->set($property, $value);
    })->with([
        'month' => ['month', '2026-08'],
        'lock version' => ['lockVersion', 99],
        'conflict flag' => ['changedElsewhere', false],
        'shared screen' => ['justShared', true],
    ])->throws(CannotUpdateLockedPropertyException::class);

    test('can\'t call the view helpers as actions', function (string $helper) {
        /** @var TestCase $this */
        editorFor($this->katie, '2026-09')->call($helper);
    })->with([
        'isShared',
        'saveStatusLabel',
        'standingLabel',
        'whenTheyCanReadLabel',
        'monthValue',
    ])->throws(MethodNotFoundException::class);
});
