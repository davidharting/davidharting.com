<?php

use App\Livewire\Memories\Editor;
use App\Memories\AddMemoryPhoto;
use App\Memories\MemoryWriter;
use App\Models\Memory;
use App\Models\MemoryPhoto;
use App\Models\User;
use App\Support\CalendarMonth;
use Database\Seeders\MemorySeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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
    return Memory::query()->where('user_id', $user->id)->where('month_id', "{$month}-01")->first();
}

/**
 * Saves the memory the way an editor open on another device would.
 */
function saveOnAnotherDevice(User $author, string $month, string $body): void
{
    $writer = new MemoryWriter($author, CalendarMonth::parseYearMonth($month));
    $writer->save($body, storedMemoryOf($author, $month)?->lock_version);
}

describe('writing', function () {
    test('opens with the viewer\'s draft', function () {
        /** @var TestCase $this */
        editorFor($this->katie, '2026-09')
            ->assertSet('body', fn (string $body) => str_starts_with($body, "Things I want to remember:\n\nThe pumpkin patch"));
    });

    test('saves as the viewer types, without sharing', function () {
        /** @var TestCase $this */
        editorFor($this->katie, '2026-09')->set('body', 'We finally fixed the gate.');

        expect(storedMemoryOf($this->katie, '2026-09'))
            ->body->toBe('We finally fixed the gate.')
            ->isDraft()->toBeTrue();
    });

    test('tells the browser when a save lands, so it can show the check', function () {
        /** @var TestCase $this */
        editorFor($this->katie, '2026-09')
            ->set('body', 'We finally fixed the gate.')
            ->assertDispatched('memory-saved');
    });

    test('offers Done, back to the viewer\'s page for the month', function () {
        /** @var TestCase $this */
        $monthPage = e(route('memories.show', ['month' => '2026-09', 'who' => 'yours']));

        editorFor($this->katie, '2026-09')
            ->assertSeeInOrder(["href=\"{$monthPage}\"", 'Done'], false);
    });

    test('names the month with its year in the link back, so an earlier month reads clearly', function () {
        /** @var TestCase $this */
        editorFor($this->katie, '2019-03')
            ->assertSeeText('← March 2019');
    });

    test('refuses text longer than a memory can be, keeping what was saved', function () {
        /** @var TestCase $this */
        editorFor($this->katie, '2026-09')
            ->set('body', str_repeat('a', MemoryWriter::MAX_LENGTH + 1))
            ->assertHasErrors('body')
            ->assertNotDispatched('memory-saved');

        expect(storedMemoryOf($this->katie, '2026-09')->body)->toStartWith('Things I want to remember:');
    });

    test('removes a draft without photos whose text is all deleted, and starts afresh after', function () {
        /** @var TestCase $this */
        storedMemoryOf($this->katie, '2026-09')->photos()->withTrashed()->forceDelete();

        $editor = editorFor($this->katie, '2026-09')
            ->set('body', '')
            ->assertSet('lockVersion', null)
            ->assertDispatched('memory-saved');

        expect(storedMemoryOf($this->katie, '2026-09'))->toBeNull();

        $editor->set('body', 'A fresh start.');

        expect(storedMemoryOf($this->katie, '2026-09')->body)->toBe('A fresh start.');
    });

    test('keeps a draft that still has photos when its text is all deleted', function () {
        /** @var TestCase $this */
        editorFor($this->katie, '2026-09')->set('body', '');

        expect(storedMemoryOf($this->katie, '2026-09'))
            ->body->toBe('')
            ->photos->not->toBeEmpty();
    });

    test('starts a memory for an empty month on the first save', function () {
        /** @var TestCase $this */
        editorFor($this->david, '2026-06')
            ->assertSet('body', '')
            ->set('body', 'June, at last.');

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
        'the other person has shared' => ['katie', '2026-09', "David has shared. Share yours to read each other's."],
        'revealed before the viewer shared' => ['david', '2026-06', 'Katie will be able to read your memory as soon as you share it.'],
        'the viewer shared before the reveal' => ['david', '2026-09', "Shared Oct 5. Edits still save as you type, and you'll read each other's memories once Katie shares, or on Nov 1."],
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

/**
 * Travels back after seeding. June has no rows, so the seeded rows for later
 * months being dated in the future doesn't affect these tests.
 */
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
            ->assertSeeText("You'll read each other's memories once Katie shares, or on Aug 1 at the latest.")
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
            ->assertSeeText('This changed on another device since you opened it.')
            ->assertSeeText('Use that one')
            ->assertSeeText('Keep this one')
            ->set('body', 'Still typing on the laptop')
            ->assertNotDispatched('memory-saved');

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
            ->assertDispatched('memory-saved');

        expect(storedMemoryOf($this->katie, '2026-09')->body)->toBe('From the laptop');
    });

    test('won\'t share over the other device\'s version', function () {
        /** @var TestCase $this */
        $this->editor->set('body', 'From the laptop')
            ->call('share')
            ->assertSet('changedElsewhere', true)
            ->assertSet('justShared', false)
            ->assertDontSeeText('Shared.');

        expect(storedMemoryOf($this->katie, '2026-09'))
            ->body->toBe('From the phone')
            ->isDraft()->toBeTrue();
    });

    test('picks up the other device\'s version on coming back with no unsaved typing, and saves on from it', function () {
        /** @var TestCase $this */
        $this->editor->call('catchUp')
            ->assertSet('body', 'From the phone')
            ->assertSet('changedElsewhere', false)
            ->assertDontSeeText('This changed on another device since you opened it.')
            ->set('body', 'From the phone, then the laptop')
            ->assertSet('changedElsewhere', false);

        expect(storedMemoryOf($this->katie, '2026-09')->body)->toBe('From the phone, then the laptop');
    });

    test('asks which version to keep on coming back with unsaved typing', function () {
        /** @var TestCase $this */
        // The browser sends unsaved typing in the same request as catchUp.
        $this->editor->update(
            calls: [['method' => 'catchUp', 'params' => [], 'path' => '']],
            updates: ['body' => 'From the laptop'],
        )
            ->assertSet('body', 'From the laptop')
            ->assertSet('changedElsewhere', true)
            ->assertSeeText('This changed on another device since you opened it.');

        expect(storedMemoryOf($this->katie, '2026-09')->body)->toBe('From the phone');
    });

    test('keeps the question and the typing on coming back while already asking', function () {
        /** @var TestCase $this */
        $this->editor->set('body', 'From the laptop')
            ->call('catchUp')
            ->assertSet('body', 'From the laptop')
            ->assertSet('changedElsewhere', true);
    });

    test('asks rather than replacing text that was refused, on coming back', function () {
        /** @var TestCase $this */
        $tooLong = str_repeat('a', MemoryWriter::MAX_LENGTH + 1);

        $this->editor->set('body', $tooLong)
            ->assertHasErrors('body')
            ->call('catchUp')
            ->assertSet('body', $tooLong)
            ->assertSet('changedElsewhere', true);
    });

    test('ignores "Keep this one" when no conflict has been detected', function () {
        /** @var TestCase $this */
        $this->editor->call('keepMine');

        expect(storedMemoryOf($this->katie, '2026-09')->body)->toBe('From the phone');
    });
});

describe('sharing', function () {
    test('says in the Share sheet that you can read each other\'s as soon as you share, once David has', function () {
        /** @var TestCase $this */
        editorFor($this->katie, '2026-09')
            ->assertSeeText('Share with David…')
            ->assertSeeText("David has shared, so you can read each other's as soon as you do.");
    });

    test('reveals the month when the second person shares', function () {
        /** @var TestCase $this */
        editorFor($this->katie, '2026-09')
            ->set('body', 'The pumpkin patch, finished.')
            ->call('share')
            ->assertSeeText('September is revealed.')
            ->assertSeeText('Read David\'s →', false);

        expect(storedMemoryOf($this->katie, '2026-09'))
            ->body->toBe('The pumpkin patch, finished.')
            ->isShared()->toBeTrue();
    });

    test('after a deadline reveal, says they can read it right away and shows the month as revealed', function () {
        /** @var TestCase $this */
        // Katie has no June memory, so there's nothing of hers to read and the button goes back to June.
        editorFor($this->david, '2026-06')
            ->assertSeeText('Katie can read it right away.')
            ->set('body', 'Better late.')
            ->call('share')
            ->assertSeeText('June is revealed.')
            ->assertSeeText('Back to June');
    });

    test('goes back to editing from the screen after sharing', function () {
        /** @var TestCase $this */
        editorFor($this->katie, '2026-09')
            ->call('share')
            ->assertSet('justShared', true)
            ->call('keepEditing')
            ->assertSet('justShared', false)
            ->assertDontSeeText('September is revealed.');
    });

    test('keeps saving edits after sharing, without offering to share again', function () {
        /** @var TestCase $this */
        editorFor($this->david, '2026-09')
            ->assertDontSeeText('Share with Katie…')
            ->set('body', 'An edit after sharing.');

        expect(storedMemoryOf($this->david, '2026-09'))
            ->body->toBe('An edit after sharing.')
            ->isShared()->toBeTrue();
    });

    test('won\'t empty a shared memory without photos', function () {
        /** @var TestCase $this */
        storedMemoryOf($this->david, '2026-09')->photos()->withTrashed()->forceDelete();

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

describe('photos', function () {
    beforeEach(function () {
        /** @var TestCase $this */
        Queue::fake();
        Storage::fake('private');
        /** Livewire keeps test uploads on this disk; point it at the private one, where real uploads land. */
        Storage::set('tmp-for-tests', Storage::disk('private'));
    });

    test('adds an uploaded photo after the others and tells the browser which it is', function () {
        /** @var TestCase $this */
        $editor = editorFor($this->katie, '2026-09')
            ->set('upload', UploadedFile::fake()->image('porch.jpg'))
            ->assertSet('upload', null)
            ->assertSet('photoCount', 2)
            ->assertDispatched('memory-saved');

        $added = storedMemoryOf($this->katie, '2026-09')->photos->last();
        expect($added->position)->toBe(1)
            ->and($added->isProcessing())->toBeTrue();
        $editor->assertDispatched('upload-handled', photoId: $added->id);
    });

    test('starts a draft with the first photo, and keeps saving text after it', function () {
        /** @var TestCase $this */
        editorFor($this->david, '2026-06')
            ->set('upload', UploadedFile::fake()->image('june.jpg'))
            ->set('body', 'And some words.')
            ->assertSet('changedElsewhere', false);

        expect(storedMemoryOf($this->david, '2026-06'))
            ->body->toBe('And some words.')
            ->isDraft()->toBeTrue()
            ->photos->toHaveCount(1);
    });

    test('turns down a file that isn\'t a photo, and lets the browser send the next', function () {
        /** @var TestCase $this */
        editorFor($this->katie, '2026-09')
            ->set('upload', UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'))
            ->assertHasErrors('upload')
            ->assertSeeText('That file isn\'t a photo the editor can take.')
            ->assertDispatched('upload-handled', photoId: null);

        expect(storedMemoryOf($this->katie, '2026-09')->photos)->toHaveCount(1);
    });

    test('turns down an eleventh photo', function () {
        /** @var TestCase $this */
        editorFor($this->david, '2026-07')
            ->assertSet('photoCount', AddMemoryPhoto::MAX_PHOTOS)
            ->assertSeeText('That\'s the most a memory can hold.', false)
            ->set('upload', UploadedFile::fake()->image('one-more.jpg'))
            ->assertHasErrors('upload')
            ->assertDispatched('upload-handled', photoId: null);

        expect(storedMemoryOf($this->david, '2026-07')->photos)->toHaveCount(AddMemoryPhoto::MAX_PHOTOS);
    });

    test('reports a photo that fails to save, and still lets the browser send the next', function () {
        /** @var TestCase $this */
        Exceptions::fake();
        MemoryPhoto::creating(fn () => throw new RuntimeException('The database went away'));

        editorFor($this->katie, '2026-09')
            ->set('upload', UploadedFile::fake()->image('porch.jpg'))
            ->assertHasErrors('upload')
            ->assertSeeText('That photo didn\'t save. Try adding it again.')
            ->assertDispatched('upload-handled', photoId: null);

        Exceptions::assertReported(RuntimeException::class);
        expect(storedMemoryOf($this->katie, '2026-09')->photos)->toHaveCount(1);
    });

    test('doesn\'t leave an empty draft behind when the first photo fails to save', function () {
        /** @var TestCase $this */
        Exceptions::fake();
        MemoryPhoto::creating(fn () => throw new RuntimeException('The database went away'));

        $editor = editorFor($this->david, '2026-06')
            ->set('upload', UploadedFile::fake()->image('june.jpg'))
            ->assertHasErrors('upload')
            ->assertSet('lockVersion', null);

        expect(storedMemoryOf($this->david, '2026-06'))->toBeNull();

        $editor->set('body', 'Words instead.')->assertSet('changedElsewhere', false);

        expect(storedMemoryOf($this->david, '2026-06')->body)->toBe('Words instead.');
    });

    test('shares a memory that has photos and no text', function () {
        /** @var TestCase $this */
        editorFor($this->david, '2026-06')
            ->set('upload', UploadedFile::fake()->image('june.jpg'))
            ->call('share')
            ->assertSet('justShared', true);

        expect(storedMemoryOf($this->david, '2026-06'))
            ->body->toBe('')
            ->isShared()->toBeTrue();
    });

    test('shows a caption chip on photos without one, and the caption on photos with one', function () {
        /** @var TestCase $this */
        editorFor($this->david, '2026-09')
            ->assertSeeText('2 of 10')
            ->assertSeeText('Our street before anyone was up')
            ->assertSeeText('✎ Caption');
    });

    test('captions a photo without bumping the memory\'s version', function () {
        /** @var TestCase $this */
        $photo = storedMemoryOf($this->david, '2026-09')->photos->last();

        editorFor($this->david, '2026-09')
            ->call('caption', $photo->id, 'The encore')
            ->assertSet('lockVersion', storedMemoryOf($this->david, '2026-09')->lock_version)
            ->assertDispatched('memory-saved')
            ->set('body', 'Text still saves.')
            ->assertSet('changedElsewhere', false);

        expect($photo->fresh()->caption)->toBe('The encore');
    });

    test('moves a photo to first', function () {
        /** @var TestCase $this */
        [$first, $second] = storedMemoryOf($this->david, '2026-09')->photos->all();

        editorFor($this->david, '2026-09')->call('movePhoto', $second->id, 0);

        expect(storedMemoryOf($this->david, '2026-09')->photos->modelKeys())->toBe([$second->id, $first->id]);
    });

    test('asks before removing a photo, tells the browser so it can offer to undo, and undoes', function () {
        /** @var TestCase $this */
        $photo = storedMemoryOf($this->david, '2026-09')->photos->first();

        $editor = editorFor($this->david, '2026-09')
            ->assertSeeHtml('wire:confirm="Remove this photo?"')
            ->call('removePhoto', $photo->id)
            ->assertDispatched('photo-removed', photoId: $photo->id)
            ->assertSet('photoCount', 1);

        expect($photo->fresh()->trashed())->toBeTrue();

        $editor->call('restorePhoto', $photo->id)->assertSet('photoCount', 2);

        expect(storedMemoryOf($this->david, '2026-09')->photos->first()->id)->toBe($photo->id);
    });

    test('won\'t remove the last photo of a shared memory with no text', function () {
        /** @var TestCase $this */
        $memory = storedMemoryOf($this->david, '2026-09');
        $memory->update(['body' => '']);
        [$kept, $other] = $memory->photos->all();
        $other->forceDelete();

        editorFor($this->david, '2026-09')
            ->call('removePhoto', $kept->id)
            ->assertHasErrors('photo')
            ->assertNotDispatched('photo-removed');

        expect($kept->fresh()->trashed())->toBeFalse();
    });

    test('tries processing a failed photo again', function () {
        /** @var TestCase $this */
        $photo = storedMemoryOf($this->katie, '2026-09')->photos->first();

        editorFor($this->katie, '2026-09')
            ->assertSeeText('This photo couldn\'t be processed.', false)
            ->call('retryPhoto', $photo->id);

        expect($photo->fresh()->isProcessing())->toBeTrue();
    });

    test('checks back only while a photo is processing', function () {
        /** @var TestCase $this */
        editorFor($this->david, '2026-09')->assertSeeHtml('wire:poll');
        editorFor($this->david, '2026-07')->assertDontSeeHtml('wire:poll');
    });

    test('leaves the other person\'s photos alone', function () {
        /** @var TestCase $this */
        $davids = storedMemoryOf($this->david, '2026-09')->photos->first();

        editorFor($this->katie, '2026-09')
            ->call('caption', $davids->id, 'Not mine to caption')
            ->call('removePhoto', $davids->id)
            ->assertNotDispatched('photo-removed');

        expect($davids->fresh())
            ->caption->toBe('Our street before anyone was up')
            ->trashed()->toBeFalse();
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
        'photo count' => ['photoCount', 0],
    ])->throws(CannotUpdateLockedPropertyException::class);

    test('can\'t call the view helpers as actions', function (string $helper) {
        /** @var TestCase $this */
        editorFor($this->katie, '2026-09')->call($helper);
    })->with([
        'isShared',
        'standingLabel',
        'whenTheyCanReadLabel',
        'monthValue',
        'photos',
    ])->throws(MethodNotFoundException::class);
});
