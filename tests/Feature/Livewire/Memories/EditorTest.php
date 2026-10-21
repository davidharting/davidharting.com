<?php

use App\Livewire\Memories\Editor;
use App\Models\Memory;
use App\Models\User;
use Database\Seeders\MemorySeeder;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The seeder counts back from the latest open month, September 2026:
 * in September David has shared and Katie has a draft. June is empty and
 * revealed at its deadline, Aug 1.
 */
beforeEach(function () {
    /** @var TestCase $this */
    $this->travelTo('2026-10-18T12:00:00Z');
    $this->seed(MemorySeeder::class);
    $this->david = User::firstWhere('email', 'david@example.com');
    $this->katie = User::firstWhere('email', 'katie@example.com');
});

function editedMemoryOf(User $user, string $month): ?Memory
{
    return Memory::query()->where('user_id', $user->id)->where('month', "{$month}-01")->first();
}

describe('writing', function () {
    test('opens with the viewer\'s draft', function () {
        /** @var TestCase $this */
        Livewire::actingAs($this->katie)->test(Editor::class, ['month' => '2026-09'])
            ->assertSet('body', editedMemoryOf($this->katie, '2026-09')->body)
            ->assertSeeText('Saved');
    });

    test('saves as the viewer types', function () {
        /** @var TestCase $this */
        Livewire::actingAs($this->katie)->test(Editor::class, ['month' => '2026-09'])
            ->set('body', 'We finally fixed the gate.')
            ->assertSeeText('Saved');

        expect(editedMemoryOf($this->katie, '2026-09'))
            ->body->toBe('We finally fixed the gate.')
            ->isDraft()->toBeTrue();
    });

    test('starts a memory for an empty month on the first save', function () {
        /** @var TestCase $this */
        Livewire::actingAs($this->david)->test(Editor::class, ['month' => '2026-06'])
            ->assertSet('body', '')
            ->assertDontSeeText('Saved')
            ->set('body', 'June, at last.');

        expect(editedMemoryOf($this->david, '2026-06')->body)->toBe('June, at last.');
    });

    test('keeps saving across several edits', function () {
        /** @var TestCase $this */
        Livewire::actingAs($this->david)->test(Editor::class, ['month' => '2026-06'])
            ->set('body', 'One')
            ->set('body', 'One, two')
            ->set('body', 'One, two, three');

        expect(editedMemoryOf($this->david, '2026-06')->body)->toBe('One, two, three');
    });

    test('says where the other person stands', function () {
        /** @var TestCase $this */
        Livewire::actingAs($this->katie)->test(Editor::class, ['month' => '2026-09'])
            ->assertSeeText('David has shared. Share yours to reveal both.');

        // Back on Jul 10, nobody had written about June yet.
        $this->travelTo('2026-07-10T12:00:00Z');
        Livewire::actingAs($this->david)->test(Editor::class, ['month' => '2026-06'])
            ->assertSeeText('Katie hasn\'t started yet.');
    });

    test('says that edits after sharing are seen once the month reveals', function () {
        /** @var TestCase $this */
        Livewire::actingAs($this->david)->test(Editor::class, ['month' => '2026-09'])
            ->assertSeeText('Shared Oct 5. Edits still save as you type, and Katie sees them once September is revealed.');
    });

    test('is not found for a month that isn\'t open', function () {
        /** @var TestCase $this */
        Livewire::actingAs($this->david)->test(Editor::class, ['month' => '2026-10'])
            ->assertNotFound();
    });

    test('is not found for someone who isn\'t a participant', function () {
        /** @var TestCase $this */
        Livewire::actingAs(User::factory()->create())->test(Editor::class, ['month' => '2026-09'])
            ->assertNotFound();
    });
});

describe('another device', function () {
    test('stops saving when the memory changed elsewhere', function () {
        /** @var TestCase $this */
        $editor = Livewire::actingAs($this->katie)->test(Editor::class, ['month' => '2026-09']);
        editedMemoryOf($this->katie, '2026-09')->forceFill(['body' => 'From the phone', 'lock_version' => 5])->save();

        $editor->set('body', 'From the laptop')
            ->assertSet('changedElsewhere', true)
            ->assertSeeText('Changed on another device')
            ->assertSeeText('Use that one')
            ->assertSeeText('Keep this one')
            ->set('body', 'Still typing on the laptop');

        expect(editedMemoryOf($this->katie, '2026-09')->body)->toBe('From the phone');
    });

    test('can take the other device\'s version', function () {
        /** @var TestCase $this */
        $editor = Livewire::actingAs($this->katie)->test(Editor::class, ['month' => '2026-09']);
        editedMemoryOf($this->katie, '2026-09')->forceFill(['body' => 'From the phone', 'lock_version' => 5])->save();

        $editor->set('body', 'From the laptop')
            ->call('useTheirs')
            ->assertSet('body', 'From the phone')
            ->assertSet('changedElsewhere', false)
            ->set('body', 'From the phone, then the laptop');

        expect(editedMemoryOf($this->katie, '2026-09')->body)->toBe('From the phone, then the laptop');
    });

    test('only overwrites the other device\'s version during a conflict', function () {
        /** @var TestCase $this */
        $editor = Livewire::actingAs($this->katie)->test(Editor::class, ['month' => '2026-09']);
        editedMemoryOf($this->katie, '2026-09')->forceFill(['body' => 'From the phone', 'lock_version' => 5])->save();

        $editor->call('keepMine');

        expect(editedMemoryOf($this->katie, '2026-09')->body)->toBe('From the phone');
    });

    test('can keep this editor\'s version', function () {
        /** @var TestCase $this */
        $editor = Livewire::actingAs($this->katie)->test(Editor::class, ['month' => '2026-09']);
        editedMemoryOf($this->katie, '2026-09')->forceFill(['body' => 'From the phone', 'lock_version' => 5])->save();

        $editor->set('body', 'From the laptop')
            ->call('keepMine')
            ->assertSet('changedElsewhere', false)
            ->assertSeeText('Saved');

        expect(editedMemoryOf($this->katie, '2026-09')->body)->toBe('From the laptop');
    });
});

describe('sharing', function () {
    test('explains what sharing does before the viewer shares', function () {
        /** @var TestCase $this */
        Livewire::actingAs($this->katie)->test(Editor::class, ['month' => '2026-09'])
            ->assertSeeText('Share with David…')
            ->assertSeeText('Only you can see this until you share it.')
            ->assertSeeText('David has shared, so September reveals as soon as you do.')
            ->assertSeeText('You can keep editing it afterwards.')
            // Static apostrophes in the template aren't escaped, so match them unescaped.
            ->assertSeeText('You can\'t unshare it.', false);
    });

    test('says when the other person can read it if they haven\'t shared', function () {
        /** @var TestCase $this */
        // Back on Jul 10, before June's Aug 1 deadline.
        $this->travelTo('2026-07-10T12:00:00Z');
        Livewire::actingAs($this->david)->test(Editor::class, ['month' => '2026-06'])
            ->assertSeeText('Katie can read it once you\'ve both shared, or on Aug 1.');
    });

    test('reveals the month when the second person shares', function () {
        /** @var TestCase $this */
        Livewire::actingAs($this->katie)->test(Editor::class, ['month' => '2026-09'])
            ->set('body', 'The pumpkin patch, finished.')
            ->call('share')
            ->assertSet('justShared', true)
            ->assertSeeText('September is revealed.')
            ->assertSeeText('Read David\'s →', false)
            ->assertSeeText('Shared · Saved');

        expect(editedMemoryOf($this->katie, '2026-09'))
            ->body->toBe('The pumpkin patch, finished.')
            ->isShared()->toBeTrue();
    });

    test('waits for the other person when sharing first', function () {
        /** @var TestCase $this */
        // Back on Jul 10, before June's Aug 1 deadline.
        $this->travelTo('2026-07-10T12:00:00Z');

        Livewire::actingAs($this->david)->test(Editor::class, ['month' => '2026-06'])
            ->set('body', 'June, at last.')
            ->call('share')
            ->assertSeeText('Shared.')
            ->assertSeeText('Reveals when Katie shares, or on Aug 1 at the latest.')
            ->assertSeeText('Back to June');
    });

    test('shares straight away after the deadline reveal', function () {
        /** @var TestCase $this */
        // June revealed empty on its Aug 1 deadline.
        Livewire::actingAs($this->david)->test(Editor::class, ['month' => '2026-06'])
            ->assertSeeText('June has already revealed, so Katie can read it right away.')
            ->set('body', 'Better late.')
            ->call('share')
            ->assertSeeText('June is revealed.')
            ->assertSeeText('Back to June');
    });

    test('keeps editing after sharing', function () {
        /** @var TestCase $this */
        Livewire::actingAs($this->david)->test(Editor::class, ['month' => '2026-09'])
            ->assertDontSeeText('Share with Katie…')
            ->assertSeeText('Shared · Saved')
            ->set('body', 'An edit after sharing.');

        expect(editedMemoryOf($this->david, '2026-09'))
            ->body->toBe('An edit after sharing.')
            ->isShared()->toBeTrue();
    });

    test('won\'t empty a shared memory', function () {
        /** @var TestCase $this */
        Livewire::actingAs($this->david)->test(Editor::class, ['month' => '2026-09'])
            ->set('body', '')
            ->assertHasErrors('body')
            ->assertSeeText('A shared memory can\'t be empty.');

        expect(editedMemoryOf($this->david, '2026-09')->body)->not->toBe('');
    });

    test('won\'t share nothing', function () {
        /** @var TestCase $this */
        Livewire::actingAs($this->david)->test(Editor::class, ['month' => '2026-06'])
            ->call('share')
            ->assertHasErrors('body');

        expect(editedMemoryOf($this->david, '2026-06'))->toBeNull();
    });
});

describe('what the browser can change', function () {
    test('can\'t switch to another month or forge a version', function (string $property, mixed $value) {
        /** @var TestCase $this */
        Livewire::actingAs($this->katie)->test(Editor::class, ['month' => '2026-09'])
            ->set($property, $value);
    })->with([
        'month' => ['month', '2026-08'],
        'lock version' => ['lockVersion', 99],
        'conflict flag' => ['changedElsewhere', false],
        'shared screen' => ['justShared', true],
    ])->throws(CannotUpdateLockedPropertyException::class);

    test('can\'t call the view helpers as actions', function () {
        /** @var TestCase $this */
        Livewire::actingAs($this->katie)->test(Editor::class, ['month' => '2026-09'])
            ->call('standingLabel');
    })->throws(MethodNotFoundException::class);
});
