<?php

use App\Livewire\Memories\ListHeading;
use App\Models\MemoryParticipant;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

beforeEach(function () {
    /** @var TestCase $this */
    $this->travelTo('2026-10-18T12:00:00Z');
    $this->david = User::factory()->create();
    $this->katie = User::factory()->create();
    MemoryParticipant::create(['user_id' => $this->david->id]);
    MemoryParticipant::create(['user_id' => $this->katie->id]);
});

test('the memories list opens the card before any month has revealed', function () {
    /** @var TestCase $this */
    // Nobody has written yet, so the list is September alone, revealing at the end of October.
    $this->actingAs($this->david)
        ->get('/memories')
        ->assertSeeText('September 2026')
        ->assertSee('Close How it works');
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
            ->assertSee('Close How it works')
            ->assertDontSee('aria-label="How it works"', false);
    });

    test('keeps the lines behind a ? once a month has revealed', function () {
        /** @var TestCase $this */
        Livewire::actingAs($this->david)
            ->test(ListHeading::class, ['anyMonthRevealed' => true])
            ->assertSeeText('Nobody sees it until you share it.')
            ->assertSee('aria-label="How it works"', false)
            ->assertDontSee('Close How it works');
    });
});

describe('dismiss()', function () {
    test('swaps the card for the ? straight away', function () {
        /** @var TestCase $this */
        Livewire::actingAs($this->david)
            ->test(ListHeading::class, ['anyMonthRevealed' => false])
            ->call('dismiss')
            ->assertDontSee('Close How it works')
            ->assertSee('aria-label="How it works"', false);
    });

    test('keeps the card closed on later visits, for the viewer only', function () {
        /** @var TestCase $this */
        Livewire::actingAs($this->david)
            ->test(ListHeading::class, ['anyMonthRevealed' => false])
            ->call('dismiss');

        Livewire::actingAs($this->david)
            ->test(ListHeading::class, ['anyMonthRevealed' => false])
            ->assertDontSee('Close How it works');

        Livewire::actingAs($this->katie)
            ->test(ListHeading::class, ['anyMonthRevealed' => false])
            ->assertSee('Close How it works');
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
