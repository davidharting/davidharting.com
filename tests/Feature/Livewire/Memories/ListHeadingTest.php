<?php

use App\Livewire\Memories\ListHeading;
use App\Models\MemoryParticipant;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The card's ✕ is only there while the card is open, and the ? only once it
 * has gone.
 */
const HOW_IT_WORKS_CARD = 'aria-label="Close How it works"';
const HOW_IT_WORKS_QUESTION_MARK = 'aria-label="How it works"';

beforeEach(function () {
    /** @var TestCase $this */
    $this->travelTo('2026-10-18T12:00:00Z');
    $this->david = MemoryParticipant::factory()->create()->user;
    $this->katie = MemoryParticipant::factory()->create()->user;
});

describe('the memories list', function () {
    test('opens the card before any month has revealed', function () {
        /** @var TestCase $this */
        // Nobody has written yet, so the list is September alone, revealing at the end of October.
        $this->actingAs($this->david)
            ->get('/memories')
            ->assertSeeText('September 2026')
            ->assertSee(HOW_IT_WORKS_CARD, false)
            ->assertDontSee(HOW_IT_WORKS_QUESTION_MARK, false);
    });
});

describe('mount()', function () {
    test('is not found for someone who is not a participant', function () {
        /** @var TestCase $this */
        Livewire::actingAs(User::factory()->create())
            ->test(ListHeading::class, ['anyMonthRevealed' => false])
            ->assertNotFound();
    });
});

describe('render()', function () {
    test('opens the card while no month has revealed', function () {
        /** @var TestCase $this */
        Livewire::actingAs($this->david)
            ->test(ListHeading::class, ['anyMonthRevealed' => false])
            ->assertSeeText('Nobody sees it until you share it.')
            ->assertSee(HOW_IT_WORKS_CARD, false)
            ->assertDontSee(HOW_IT_WORKS_QUESTION_MARK, false);
    });

    test('hides the card behind a ? once a month has revealed', function () {
        /** @var TestCase $this */
        Livewire::actingAs($this->david)
            ->test(ListHeading::class, ['anyMonthRevealed' => true])
            ->assertSeeText('Nobody sees it until you share it.')
            ->assertSee(HOW_IT_WORKS_QUESTION_MARK, false)
            ->assertDontSee(HOW_IT_WORKS_CARD, false);
    });
});

describe('dismiss()', function () {
    test('swaps the card for the ? straight away', function () {
        /** @var TestCase $this */
        Livewire::actingAs($this->david)
            ->test(ListHeading::class, ['anyMonthRevealed' => false])
            ->call('dismiss')
            ->assertDontSee(HOW_IT_WORKS_CARD, false)
            ->assertSee(HOW_IT_WORKS_QUESTION_MARK, false);
    });

    test('keeps the card closed on later visits', function () {
        /** @var TestCase $this */
        Livewire::actingAs($this->david)
            ->test(ListHeading::class, ['anyMonthRevealed' => false])
            ->call('dismiss');

        Livewire::actingAs($this->david)
            ->test(ListHeading::class, ['anyMonthRevealed' => false])
            ->assertDontSee(HOW_IT_WORKS_CARD, false);
    });

    test('leaves the card open for the other participant', function () {
        /** @var TestCase $this */
        Livewire::actingAs($this->david)
            ->test(ListHeading::class, ['anyMonthRevealed' => false])
            ->call('dismiss');

        Livewire::actingAs($this->katie)
            ->test(ListHeading::class, ['anyMonthRevealed' => false])
            ->assertSee(HOW_IT_WORKS_CARD, false);
    });

    test('keeps the time it was first closed', function () {
        /** @var TestCase $this */
        $component = Livewire::actingAs($this->david)
            ->test(ListHeading::class, ['anyMonthRevealed' => false])
            ->call('dismiss');

        $this->travel(1)->day();
        $component->call('dismiss');

        expect($this->david->memoryParticipant->fresh()->how_it_works_dismissed_at->toIso8601ZuluString())
            ->toBe('2026-10-18T12:00:00Z');
    });
});
