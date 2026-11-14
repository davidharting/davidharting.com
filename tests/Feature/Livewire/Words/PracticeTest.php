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
function practisedWord(string $word): Word
{
    $practised = Word::factory()->create(['word' => $word]);
    $practised->flashcard->grade(Rating::Good);

    return $practised;
}

describe('the practice page', function () {
    test('is only for admins', function () {
        /** @var TestCase $this */
        $this->get(route('words.practice'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->get(route('words.practice'))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->get(route('words.practice'))
            ->assertOk()
            ->assertSeeLivewire(Practice::class);
    });

    test('deals the due words with their short definitions', function () {
        /** @var TestCase $this */
        Word::factory()->create(['word' => 'petrichor', 'short_definition' => 'the smell of rain on dry ground']);
        practisedWord('quotidian');

        Livewire::actingAs($this->admin)
            ->test(Practice::class)
            ->assertSee('petrichor')
            ->assertSee('the smell of rain on dry ground')
            ->assertDontSee('quotidian');
    });

    test('says there are no words yet when the list is empty', function () {
        /** @var TestCase $this */
        Livewire::actingAs($this->admin)
            ->test(Practice::class)
            ->assertSeeText('No words yet')
            ->assertDontSeeText('All caught up');
    });

    test('says when the next word is due once everything is practised', function () {
        /** @var TestCase $this */
        practisedWord('quotidian');

        Livewire::actingAs($this->admin)
            ->test(Practice::class)
            ->assertSeeText('All caught up')
            ->assertSee('10 minutes from now');
    });
});

describe('mount()', function () {
    test('is forbidden to anyone who is not an admin', function () {
        /** @var TestCase $this */
        Livewire::actingAs(User::factory()->create())
            ->test(Practice::class)
            ->assertForbidden();
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

    test('returns when the next word is due', function () {
        /** @var TestCase $this */
        $flashcard = Word::factory()->create()->flashcard;

        Livewire::actingAs($this->admin)
            ->test(Practice::class)
            ->call('grade', $flashcard->id, false)
            ->assertReturned('1 minute from now');
    });

});

describe('practiseAhead()', function () {
    test('deals the next words even though none are due', function () {
        /** @var TestCase $this */
        practisedWord('quotidian');

        Livewire::actingAs($this->admin)
            ->test(Practice::class)
            ->call('practiseAhead')
            ->assertReturned(fn (array $cards): bool => array_column($cards, 'word') === ['quotidian']);
    });
});
