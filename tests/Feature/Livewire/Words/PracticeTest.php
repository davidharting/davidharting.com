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
 * A word whose flashcard was just graded Got it, so it isn't due for a while.
 */
function practiceTestPractisedWord(string $word): Word
{
    $practised = Word::factory()->create(['word' => $word]);
    $practised->flashcard->grade(Rating::Good);

    return $practised;
}

/**
 * The words drawn in a round, in order.
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
    test('draws only the due words, with their short definitions', function () {
        /** @var TestCase $this */
        Word::factory()->create(['word' => 'petrichor', 'short_definition' => 'the smell of rain on dry ground']);
        practiceTestPractisedWord('quotidian');

        $round = Livewire::actingAs($this->admin)->test(Practice::class)->instance()->dueRound();

        expect($round['cards'])->toHaveCount(1)
            ->and($round['cards'][0])->toMatchArray(['word' => 'petrichor', 'definition' => 'the smell of rain on dry ground', 'due' => true]);
    });

    test('draws the most overdue word first', function () {
        /** @var TestCase $this */
        // Made second, so ordering by id alone would put it last.
        Word::factory()->create(['word' => 'newer']);
        $this->travel(-2)->days(fn () => Word::factory()->create(['word' => 'older']));

        $round = Livewire::actingAs($this->admin)->test(Practice::class)->instance()->dueRound();

        expect(practiceTestWords($round['cards']))->toBe(['older', 'newer']);
    });

    test('draws a word added within the same second', function () {
        /** @var TestCase $this */
        // Let the clock run, so the word falls due a fraction of a second
        // after the whole second the query would otherwise compare against.
        $this->travelBack();
        Word::factory()->create(['word' => 'brand-new']);

        $round = Livewire::actingAs($this->admin)->test(Practice::class)->instance()->dueRound();

        expect(practiceTestWords($round['cards']))->toBe(['brand-new']);
    });

    test('draws five words a round, and counts every word due', function () {
        /** @var TestCase $this */
        Word::factory()->count(7)->create();

        $round = Livewire::actingAs($this->admin)->test(Practice::class)->instance()->dueRound();

        expect($round['cards'])->toHaveCount(5)
            ->and($round['dueCount'])->toBe(7);
    });

    test('says when the next word is due once nothing is', function () {
        /** @var TestCase $this */
        $word = practiceTestPractisedWord('quotidian');

        $round = Livewire::actingAs($this->admin)->test(Practice::class)->instance()->dueRound();

        expect($round['cards'])->toBe([])
            ->and($round['nextDueAt'])->toBe($word->flashcard->fresh()->schedule->due->format(DATE_ATOM));
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
            ->assertReturned(true)
            ->call('grade', $missedIt->id, false);

        expect($gotIt->reviews()->sole()->rating)->toBe(Rating::Good)
            ->and($missedIt->reviews()->sole()->rating)->toBe(Rating::Again);
    });
});

describe('practiseAhead()', function () {
    test('draws the next words even though none are due, marked as not due', function () {
        /** @var TestCase $this */
        practiceTestPractisedWord('quotidian');

        $cards = Livewire::actingAs($this->admin)->test(Practice::class)->instance()->practiseAhead();

        expect(practiceTestWords($cards))->toBe(['quotidian'])
            ->and($cards[0]['due'])->toBeFalse();
    });
});
