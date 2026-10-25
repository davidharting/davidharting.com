<?php

use App\Livewire\Memories\Reactions;
use App\Models\Memory;
use App\Models\User;
use App\Support\CalendarMonth;
use Database\Seeders\MemorySeeder;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The seeder counts back from the latest open month, September 2026, as of
 * Oct 18.
 *
 * - September hasn't revealed: David shared, Katie has a draft.
 * - August revealed. Katie reacted 😂 to David's; David reacted ❤️ 🥹 to Katie's.
 * - July revealed. Katie left five reactions on David's, the most she can.
 */
beforeEach(function () {
    /** @var TestCase $this */
    $this->travelTo('2026-10-18T12:00:00Z');
    $this->seed(MemorySeeder::class);
    $this->david = User::firstWhere('email', 'david@example.com');
    $this->katie = User::firstWhere('email', 'katie@example.com');
});

function seededMemory(User $author, int $month): Memory
{
    return Memory::query()
        ->whereBelongsTo($author)
        ->where('month_id', CalendarMonth::of(2026, $month)->toDateString())
        ->sole();
}

/**
 * @return list<string>
 */
function emojiOn(Memory $memory): array
{
    return $memory->reactions()->pluck('emoji')->all();
}

describe('mount()', function () {
    test('is not found for a memory the viewer may not read yet', function () {
        /** @var TestCase $this */
        Livewire::actingAs($this->katie)
            ->test(Reactions::class, ['memory' => seededMemory($this->david, 9)])
            ->assertNotFound();
    });
});

describe('add()', function () {
    test("adds the viewer's emoji after their others", function () {
        /** @var TestCase $this */
        $katies = seededMemory($this->katie, 8);

        Livewire::actingAs($this->david)
            ->test(Reactions::class, ['memory' => $katies])
            ->call('add', '🎉')
            ->assertSeeHtml('aria-label="Remove your 🎉 reaction"');

        expect(emojiOn($katies))->toBe(['❤️', '🥹', '🎉']);
    });

    test('quietly ignores anything that is not one emoji', function () {
        /** @var TestCase $this */
        $katies = seededMemory($this->katie, 8);

        Livewire::actingAs($this->david)
            ->test(Reactions::class, ['memory' => $katies])
            ->call('add', 'hello')
            ->assertOk();

        expect(emojiOn($katies))->toBe(['❤️', '🥹']);
    });

    test("is not found on the viewer's own memory", function () {
        /** @var TestCase $this */
        $davids = seededMemory($this->david, 8);

        Livewire::actingAs($this->david)
            ->test(Reactions::class, ['memory' => $davids])
            ->call('add', '🎉')
            ->assertNotFound();

        expect(emojiOn($davids))->toBe(['😂']);
    });
});

describe('remove() and undo()', function () {
    test('removes the reaction, and Undo puts it back in its place', function () {
        /** @var TestCase $this */
        $katies = seededMemory($this->katie, 8);
        $component = Livewire::actingAs($this->david)->test(Reactions::class, ['memory' => $katies]);

        $component->call('remove', '❤️');
        expect(emojiOn($katies))->toBe(['🥹']);

        $component->call('undo');
        expect(emojiOn($katies))->toBe(['❤️', '🥹']);
    });

    test('Undo does nothing when nothing was removed', function () {
        /** @var TestCase $this */
        $katies = seededMemory($this->katie, 8);

        Livewire::actingAs($this->david)
            ->test(Reactions::class, ['memory' => $katies])
            ->call('undo');

        expect(emojiOn($katies))->toBe(['❤️', '🥹']);
    });
});

describe('the row', function () {
    test('lets the viewer remove theirs and add more', function () {
        /** @var TestCase $this */
        Livewire::actingAs($this->david)
            ->test(Reactions::class, ['memory' => seededMemory($this->katie, 8)])
            ->assertSeeHtmlInOrder(['Remove your ❤️ reaction', 'Remove your 🥹 reaction', 'Add a reaction']);
    });

    test("shows the other person's reactions read-only on the viewer's own memory", function () {
        /** @var TestCase $this */
        Livewire::actingAs($this->david)
            ->test(Reactions::class, ['memory' => seededMemory($this->david, 8)])
            ->assertSeeHtml('aria-label="Katie reacted 😂"')
            ->assertDontSeeHtml('Remove your')
            ->assertDontSeeHtml('Add a reaction');
    });

    test('hides the add button once the viewer has left five', function () {
        /** @var TestCase $this */
        Livewire::actingAs($this->katie)
            ->test(Reactions::class, ['memory' => seededMemory($this->david, 7)])
            ->assertSeeHtml('Remove your 🎉 reaction')
            ->assertDontSeeHtml('Add a reaction');
    });
});
