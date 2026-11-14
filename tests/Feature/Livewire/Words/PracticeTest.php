<?php

use App\Fsrs\Rating;
use App\Livewire\Words\Practice;
use App\Models\User;
use App\Models\Word;
use Livewire\Livewire;
use Tests\TestCase;

beforeEach(function () {
    /** @var TestCase $this */
    $this->freezeTime();
    $this->admin = User::factory()->admin()->create();
});

/**
 * A word whose flashcard was just graded Got it, so it isn't due again for
 * ten minutes.
 */
function practiceTestPractisedWord(string $word): Word
{
    $practised = Word::factory()->create(['word' => $word]);
    $practised->flashcard->grade(Rating::Good);

    return $practised;
}

/**
 * The words dealt in a round, in order.
 *
 * @param  list<array{word: string}>  $cards
 * @return list<string>
 */
function practiceTestWords(array $cards): array
{
    return array_column($cards, 'word');
}

describe('the practice page', function () {
    test('sends guests to log in', function () {
        /** @var TestCase $this */
        $this->get(route('words.practice'))->assertRedirect(route('login'));
    });

    test('is forbidden to anyone who is not an admin', function () {
        /** @var TestCase $this */
        $this->actingAs(User::factory()->create())
            ->get(route('words.practice'))
            ->assertForbidden();

        Livewire::actingAs(User::factory()->create())
            ->test(Practice::class)
            ->assertForbidden();
    });

    test('opens for an admin', function () {
        /** @var TestCase $this */
        $this->actingAs($this->admin)
            ->get(route('words.practice'))
            ->assertOk()
            ->assertSeeLivewire(Practice::class);
    });

    test('says there are no words yet when the list is empty', function () {
        /** @var TestCase $this */
        Livewire::actingAs($this->admin)
            ->test(Practice::class)
            ->assertSeeText('No words yet')
            ->assertDontSeeText('All caught up');
    });
});

describe('dueRound()', function () {
    test('deals only the due words, with their short definitions', function () {
        /** @var TestCase $this */
        Word::factory()->create(['word' => 'petrichor', 'short_definition' => 'the smell of rain on dry ground']);
        practiceTestPractisedWord('quotidian');

        $round = Livewire::actingAs($this->admin)->test(Practice::class)->instance()->dueRound();

        expect($round['cards'])->toHaveCount(1)
            ->and($round['cards'][0])->toMatchArray(['word' => 'petrichor', 'definition' => 'the smell of rain on dry ground']);
    });

    test('deals the most overdue word first', function () {
        /** @var TestCase $this */
        $this->travel(-2)->days();
        Word::factory()->create(['word' => 'older']);
        $this->travelBack();
        $this->freezeTime();
        Word::factory()->create(['word' => 'newer']);

        $round = Livewire::actingAs($this->admin)->test(Practice::class)->instance()->dueRound();

        expect(practiceTestWords($round['cards']))->toBe(['older', 'newer']);
    });

    test('deals a word added a moment ago', function () {
        /** @var TestCase $this */
        $this->travelBack();
        Word::factory()->create(['word' => 'brand-new']);

        $round = Livewire::actingAs($this->admin)->test(Practice::class)->instance()->dueRound();

        expect(practiceTestWords($round['cards']))->toBe(['brand-new']);
    });

    test('deals at most one round of words at a time', function () {
        /** @var TestCase $this */
        Word::factory()->count(Practice::ROUND_SIZE + 1)->create();

        $round = Livewire::actingAs($this->admin)->test(Practice::class)->instance()->dueRound();

        expect($round['cards'])->toHaveCount(Practice::ROUND_SIZE);
    });

    test('says when the next word is due once nothing is', function () {
        /** @var TestCase $this */
        practiceTestPractisedWord('quotidian');

        $round = Livewire::actingAs($this->admin)->test(Practice::class)->instance()->dueRound();

        expect($round['cards'])->toBe([])
            ->and($round['nextDueAt'])->toBe(now()->addMinutes(10)->utc()->format(DATE_ATOM));
    });
});

describe('grade()', function () {
    test('saves Got it as Good and Missed it as Again', function () {
        /** @var TestCase $this */
        $gotIt = Word::factory()->create()->flashcard;
        $missedIt = Word::factory()->create()->flashcard;

        Livewire::actingAs($this->admin)
            ->test(Practice::class)
            ->call('grade', $gotIt->id, true)
            ->call('grade', $missedIt->id, false);

        expect($gotIt->reviews()->sole()->rating)->toBe(Rating::Good)
            ->and($missedIt->reviews()->sole()->rating)->toBe(Rating::Again);
    });
});

describe('practiseAhead()', function () {
    test('deals the next words even though none are due', function () {
        /** @var TestCase $this */
        practiceTestPractisedWord('quotidian');

        Livewire::actingAs($this->admin)
            ->test(Practice::class)
            ->call('practiseAhead')
            ->assertReturned(fn (array $cards): bool => practiceTestWords($cards) === ['quotidian']);
    });
});
