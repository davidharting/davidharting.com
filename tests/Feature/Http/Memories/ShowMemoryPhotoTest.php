<?php

use App\Models\Memory;
use App\Models\MemoryParticipant;
use App\Models\MemoryPhoto;
use App\Models\User;
use App\Support\CalendarMonth;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * The clock sits mid-October 2026: September has opened but not revealed,
 * and August has revealed. David and Katie are the two participants.
 */
beforeEach(function () {
    /** @var TestCase $this */
    $this->travelTo('2026-10-18T12:00:00Z');
    [$this->david, $this->katie] = User::factory()->count(2)->create();
    MemoryParticipant::create(['user_id' => $this->david->id]);
    MemoryParticipant::create(['user_id' => $this->katie->id]);

    $this->davidsSeptemberPhoto = function (): MemoryPhoto {
        $memory = Memory::factory()->for($this->david)->forMonth(CalendarMonth::of(2026, 9))->shared()->create();

        return MemoryPhoto::factory()->for($memory)->ready()->create();
    };

    $this->davidsAugustPhoto = function (): MemoryPhoto {
        $memory = Memory::factory()->for($this->david)->forMonth(CalendarMonth::of(2026, 8))->shared(CarbonImmutable::parse('2026-09-05T12:00:00Z'))->create();

        return MemoryPhoto::factory()->for($memory)->ready()->create();
    };
});

test('redirects the author to a signed link to the web variant', function () {
    /** @var TestCase $this */
    $photo = ($this->davidsSeptemberPhoto)();

    $response = $this->actingAs($this->david)->get(route('memories.photos.show', [$photo, 'web']));

    $response->assertRedirectContains($photo->web_path)->assertRedirectContains('signature=');
    expect($response->headers->get('Cache-Control'))->toContain('private')->toContain('max-age=3000');
});

test('redirects to the thumbnail', function () {
    /** @var TestCase $this */
    $photo = ($this->davidsSeptemberPhoto)();

    $this->actingAs($this->david)->get(route('memories.photos.show', [$photo, 'thumbnail']))
        ->assertRedirectContains($photo->thumbnail_path);
});

test('redirects the other participant once the month has revealed', function () {
    /** @var TestCase $this */
    $photo = ($this->davidsAugustPhoto)();

    $this->actingAs($this->katie)->get(route('memories.photos.show', [$photo, 'web']))
        ->assertRedirectContains($photo->web_path);
});

test('is not found for the other participant before the month reveals', function () {
    /** @var TestCase $this */
    $photo = ($this->davidsSeptemberPhoto)();

    $this->actingAs($this->katie)->get(route('memories.photos.show', [$photo, 'web']))->assertNotFound();
});

test('is not found for someone who is not a participant', function () {
    /** @var TestCase $this */
    $photo = ($this->davidsAugustPhoto)();

    $this->actingAs(User::factory()->create())->get(route('memories.photos.show', [$photo, 'web']))->assertNotFound();
});

test('is not found for a guest', function () {
    /** @var TestCase $this */
    $photo = ($this->davidsAugustPhoto)();

    $this->get(route('memories.photos.show', [$photo, 'web']))->assertNotFound();
});

test('is not found once the photo is removed, even for its author', function () {
    /** @var TestCase $this */
    $photo = ($this->davidsSeptemberPhoto)();
    $photo->delete();

    $this->actingAs($this->david)->get(route('memories.photos.show', [$photo, 'web']))->assertNotFound();
});

test('is not found while the photo is still processing, even for its author', function () {
    /** @var TestCase $this */
    $memory = Memory::factory()->for($this->david)->forMonth(CalendarMonth::of(2026, 9))->create();
    $photo = MemoryPhoto::factory()->for($memory)->create();

    $this->actingAs($this->david)->get(route('memories.photos.show', [$photo, 'thumbnail']))->assertNotFound();
});

test('is not found for a variant that does not exist', function () {
    /** @var TestCase $this */
    $photo = ($this->davidsSeptemberPhoto)();

    $this->actingAs($this->david)->get("/memories/photos/{$photo->id}/original")->assertNotFound();
});
