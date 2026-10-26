<?php

use App\Livewire\Memories\Reactions;
use App\Models\Memory;
use App\Models\MemoryParticipant;
use App\Models\User;
use App\Support\CalendarMonth;
use Database\Seeders\MemorySeeder;
use Illuminate\Support\Once;
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

function reactionsTestMemory(User $author, int $month): Memory
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
        $davidsUnrevealed = reactionsTestMemory($this->david, 9);

        Livewire::actingAs($this->katie)
            ->test(Reactions::class, ['memory' => $davidsUnrevealed])
            ->assertNotFound();
    });
});

describe('hydrate()', function () {
    test('is not found once the viewer may no longer read the memory', function () {
        /** @var TestCase $this */
        $component = Livewire::actingAs($this->david)->test(Reactions::class, ['memory' => reactionsTestMemory($this->katie, 8)]);
        MemoryParticipant::query()->where('user_id', $this->katie->id)->delete();
        Once::flush();

        $component->call('$refresh')->assertNotFound();
    });
});

describe('add()', function () {
    test("adds the viewer's emoji after their others", function () {
        /** @var TestCase $this */
        $katiesRevealed = reactionsTestMemory($this->katie, 8);

        Livewire::actingAs($this->david)
            ->test(Reactions::class, ['memory' => $katiesRevealed])
            ->call('add', '🎉')
            ->assertSeeHtml('aria-label="Remove your 🎉 reaction"');

        expect(emojiOn($katiesRevealed))->toBe(['❤️', '🥹', '🎉']);
    });

    test('refuses anything that is not one emoji, without failing the request', function () {
        /** @var TestCase $this */
        $katiesRevealed = reactionsTestMemory($this->katie, 8);

        Livewire::actingAs($this->david)
            ->test(Reactions::class, ['memory' => $katiesRevealed])
            ->call('add', 'hello')
            ->assertHasErrors('emoji');

        expect(emojiOn($katiesRevealed))->toBe(['❤️', '🥹']);
    });

    test('refuses a sixth reaction and says why', function () {
        /** @var TestCase $this */
        $davidsWithKatiesFive = reactionsTestMemory($this->david, 7);

        Livewire::actingAs($this->katie)
            ->test(Reactions::class, ['memory' => $davidsWithKatiesFive])
            ->call('add', '🥹')
            ->assertSeeText('You can leave up to 5 reactions on a memory.');

        expect(emojiOn($davidsWithKatiesFive))->toHaveCount(5);
    });
});

describe('add(), remove() and undo()', function () {
    test("are not found on the viewer's own memory", function (string $method) {
        /** @var TestCase $this */
        $davidsRevealed = reactionsTestMemory($this->david, 8);

        Livewire::actingAs($this->david)
            ->test(Reactions::class, ['memory' => $davidsRevealed])
            ->call($method, '😂')
            ->assertNotFound();

        expect(emojiOn($davidsRevealed))->toBe(['😂']);
    })->with(['add', 'remove', 'undo']);
});

describe('remove() and undo()', function () {
    test('removes the reaction, and Undo puts it back in its place', function () {
        /** @var TestCase $this */
        $katiesRevealed = reactionsTestMemory($this->katie, 8);
        $component = Livewire::actingAs($this->david)->test(Reactions::class, ['memory' => $katiesRevealed]);

        $component->call('remove', '❤️')->assertReturned(true);
        expect(emojiOn($katiesRevealed))->toBe(['🥹']);

        $component->call('undo');
        expect(emojiOn($katiesRevealed))->toBe(['❤️', '🥹']);
    });

    test("removing an emoji the viewer hadn't left offers nothing to undo", function () {
        /** @var TestCase $this */
        $katiesRevealed = reactionsTestMemory($this->katie, 8);
        $component = Livewire::actingAs($this->david)->test(Reactions::class, ['memory' => $katiesRevealed]);

        $component->call('remove', '❤️');
        $component->call('remove', '🎉')->assertReturned(false);
        $component->call('undo');

        expect(emojiOn($katiesRevealed))->toBe(['🥹']);
    });

    test('Undo does nothing when nothing was removed', function () {
        /** @var TestCase $this */
        $katiesRevealed = reactionsTestMemory($this->katie, 8);

        Livewire::actingAs($this->david)
            ->test(Reactions::class, ['memory' => $katiesRevealed])
            ->call('undo');

        expect(emojiOn($katiesRevealed))->toBe(['❤️', '🥹']);
    });

    test('Undo refuses once the viewer has filled up their reactions again', function () {
        /** @var TestCase $this */
        $davidsWithKatiesFive = reactionsTestMemory($this->david, 7);
        $component = Livewire::actingAs($this->katie)->test(Reactions::class, ['memory' => $davidsWithKatiesFive]);

        $component->call('remove', '❤️')->call('add', '🥹')->call('undo')
            ->assertHasErrors('emoji');

        expect(emojiOn($davidsWithKatiesFive))->not->toContain('❤️')->toHaveCount(5);
    });
});

describe('render()', function () {
    test('lets the viewer remove theirs and add more', function () {
        /** @var TestCase $this */
        Livewire::actingAs($this->david)
            ->test(Reactions::class, ['memory' => reactionsTestMemory($this->katie, 8)])
            ->assertSeeHtmlInOrder(['Remove your ❤️ reaction', 'Remove your 🥹 reaction', 'Add a reaction']);
    });

    test("shows the other person's reactions read-only on the viewer's own memory", function () {
        /** @var TestCase $this */
        Livewire::actingAs($this->david)
            ->test(Reactions::class, ['memory' => reactionsTestMemory($this->david, 8)])
            ->assertSeeHtml('aria-label="Katie reacted 😂"')
            ->assertDontSeeHtml('Remove your')
            ->assertDontSeeHtml('Add a reaction');
    });

    test('offers only the quick picks the viewer has not left yet', function () {
        /** @var TestCase $this */
        Livewire::actingAs($this->david)
            ->test(Reactions::class, ['memory' => reactionsTestMemory($this->katie, 8)])
            ->assertSeeHtml('aria-label="React 😂"')
            ->assertDontSeeHtml('aria-label="React ❤️"')
            ->assertDontSeeHtml('aria-label="React 🥹"');
    });

    test('hides the add button once the viewer has left five', function () {
        /** @var TestCase $this */
        Livewire::actingAs($this->katie)
            ->test(Reactions::class, ['memory' => reactionsTestMemory($this->david, 7)])
            ->assertSeeHtml('Remove your 🎉 reaction')
            ->assertDontSeeHtml('Add a reaction');
    });
});
