<?php

use App\Memories\MemoryReactionWriter;
use App\Models\Memory;
use App\Models\MemoryReaction;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

beforeEach(function () {
    /** @var TestCase $this */
    $this->travelTo('2026-10-18T12:00:00Z');
    $this->memory = Memory::factory()->shared()->create();
    $this->katie = User::factory()->create();
    $this->writer = new MemoryReactionWriter($this->katie, $this->memory);
});

/**
 * The emoji the user has left on the memory, in order.
 *
 * @return list<string>
 */
function reactionsOf(User $user, Memory $memory): array
{
    return $memory->reactions()->where('user_id', $user->id)->pluck('emoji')->all();
}

describe('add()', function () {
    test('adds reactions in the order they were left', function () {
        /** @var TestCase $this */
        $this->writer->add('❤️');
        $this->travel(1)->minute();
        $this->writer->add('😂');

        expect(reactionsOf($this->katie, $this->memory))->toBe(['❤️', '😂']);
    });

    test('accepts skin tones, joined sequences, flags and keycaps', function (string $emoji) {
        /** @var TestCase $this */
        $this->writer->add($emoji);

        expect(reactionsOf($this->katie, $this->memory))->toBe([$emoji]);
    })->with(['👍🏽', '👨‍👩‍👧', '🇺🇸', '1️⃣']);

    test('refuses anything that is not exactly one emoji', function (string $text) {
        /** @var TestCase $this */
        expect(fn () => $this->writer->add($text))->toThrow(ValidationException::class, 'A reaction is a single emoji.');

        expect(reactionsOf($this->katie, $this->memory))->toBe([]);
    })->with(['', 'a', 'lol', '❤️❤️', '❤️ ', '😂a']);

    test('ignores an emoji the reactor already left', function () {
        /** @var TestCase $this */
        $this->writer->add('❤️');
        $this->writer->add('❤️');

        expect(reactionsOf($this->katie, $this->memory))->toBe(['❤️']);
    });

    test('refuses a sixth reaction from the same person', function () {
        /** @var TestCase $this */
        foreach (['❤️', '😂', '🥹', '🎉', '😍'] as $emoji) {
            $this->writer->add($emoji);
        }

        expect(fn () => $this->writer->add('👏'))->toThrow(ValidationException::class, 'up to 5 reactions');

        expect(reactionsOf($this->katie, $this->memory))->toHaveCount(5);
    });

    test("doesn't count anyone else's reactions toward the limit", function () {
        /** @var TestCase $this */
        MemoryReaction::factory()->for($this->memory)->count(5)
            ->sequence(...array_map(fn ($emoji) => ['emoji' => $emoji], ['❤️', '😂', '🥹', '🎉', '😍']))
            ->create();

        $this->writer->add('❤️');

        expect(reactionsOf($this->katie, $this->memory))->toBe(['❤️']);
    });
});

describe('remove()', function () {
    test('removes the reaction and returns it so it can be restored', function () {
        /** @var TestCase $this */
        $this->writer->add('❤️');

        $removed = $this->writer->remove('❤️');

        expect($removed->emoji)->toBe('❤️')
            ->and(reactionsOf($this->katie, $this->memory))->toBe([]);
    });

    test("leaves someone else's same emoji alone", function () {
        /** @var TestCase $this */
        $other = MemoryReaction::factory()->for($this->memory)->create(['emoji' => '❤️']);

        expect($this->writer->remove('❤️'))->toBeNull()
            ->and($other->fresh())->not->toBeNull();
    });
});

describe('restore()', function () {
    test('puts a removed reaction back in its original place', function () {
        /** @var TestCase $this */
        foreach (['❤️', '😂', '🥹'] as $emoji) {
            $this->writer->add($emoji);
            $this->travel(1)->minute();
        }

        $removed = $this->writer->remove('😂');
        $this->writer->restore($removed->emoji, $removed->created_at);

        expect(reactionsOf($this->katie, $this->memory))->toBe(['❤️', '😂', '🥹']);
    });

    test('refuses when the reactor filled up their reactions in the meantime', function () {
        /** @var TestCase $this */
        $this->writer->add('❤️');
        $removed = $this->writer->remove('❤️');
        foreach (['😂', '🥹', '🎉', '😍', '👏'] as $emoji) {
            $this->writer->add($emoji);
        }

        expect(fn () => $this->writer->restore($removed->emoji, $removed->created_at))
            ->toThrow(ValidationException::class, 'up to 5 reactions');
    });
});
