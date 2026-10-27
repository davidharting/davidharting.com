<?php

use App\Models\User;
use Database\Seeders\MemorySeeder;
use Tests\Support\RenderedHead;
use Tests\TestCase;

/**
 * The seeder's launch month is March 2026, so the picker offers January 2016
 * to February 2026.
 */
beforeEach(function () {
    /** @var TestCase $this */
    $this->travelTo('2026-10-18T12:00:00Z');
    $this->seed(MemorySeeder::class);
    $this->actingAs(User::firstWhere('email', 'katie@example.com'));
});

describe('the picker page', function () {
    test('offers a month picker from January 2016 to the month before launch, starting at the latest', function () {
        /** @var TestCase $this */
        $response = $this->get('/memories/earlier')
            ->assertSuccessful()
            ->assertSee('type="month"', false)
            ->assertSee('min="2016-01"', false)
            ->assertSee('max="2026-02"', false)
            ->assertSee('value="2026-02"', false)
            ->assertSeeText('Write');

        expect(RenderedHead::from($response)->title)->toBe('An earlier month - davidharting.com');
    });

    test('links back to the list', function () {
        /** @var TestCase $this */
        $this->get('/memories/earlier')
            ->assertSee('href="'.route('memories.index').'"', false);
    });
});

describe('picking a month', function () {
    test('opens the editor for the month', function () {
        /** @var TestCase $this */
        $this->post('/memories/earlier', ['month' => '2019-03'])
            ->assertRedirect(route('memories.write', '2019-03'));
    });

    test('accepts both ends of the range', function (string $month) {
        /** @var TestCase $this */
        $this->post('/memories/earlier', ['month' => $month])
            ->assertRedirect(route('memories.write', $month));
    })->with(['2016-01', '2026-02']);

    test('sends the viewer back with a hint for a month outside the range or not a month', function (?string $month) {
        /** @var TestCase $this */
        $this->from('/memories/earlier')
            ->post('/memories/earlier', ['month' => $month])
            ->assertRedirect('/memories/earlier')
            ->assertSessionHasErrors(['month' => 'Pick a month from January 2016 to February 2026.']);
    })->with([
        'the launch month' => '2026-03',
        'before 2016' => '2015-12',
        'not a month' => '2019-13',
        'a whole date' => '2019-03-01',
        'nothing' => null,
    ]);
});
