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
 * and August has revealed. David and Katie became
 * the two participants back in March.
 */
beforeEach(function () {
    /** @var TestCase $this */
    $this->travelTo('2026-10-18T12:00:00Z');
    [$this->david, $this->katie] = User::factory()->count(2)->create();
    MemoryParticipant::factory()->for($this->david)->create(['created_at' => '2026-03-15T12:00:00Z']);
    MemoryParticipant::factory()->for($this->katie)->create(['created_at' => '2026-03-15T12:00:00Z']);
});

/**
 * A ready photo on David's shared September memory, which hasn't revealed.
 */
function photoBeforeTheReveal(User $david): MemoryPhoto
{
    $memory = Memory::factory()->for($david)->forMonth(CalendarMonth::of(2026, 9))->shared()->create();

    return MemoryPhoto::factory()->for($memory)->ready()->create();
}

/**
 * A ready photo on David's shared August memory, which has revealed.
 */
function photoAfterTheReveal(User $david): MemoryPhoto
{
    $memory = Memory::factory()->for($david)->forMonth(CalendarMonth::of(2026, 8))->shared(CarbonImmutable::parse('2026-09-05T12:00:00Z'))->create();

    return MemoryPhoto::factory()->for($memory)->ready()->create();
}

test('redirects the author to a signed link to the web variant', function () {
    /** @var TestCase $this */
    $photo = photoBeforeTheReveal($this->david);

    $this->actingAs($this->david)->get(route('memories.photos.show', [$photo, 'web']))
        ->assertRedirectContains($photo->web_path)
        ->assertRedirectContains('signature=');
});

test('lets the browser cache the redirect privately for less than the link lasts', function () {
    /** @var TestCase $this */
    $photo = photoBeforeTheReveal($this->david);

    $response = $this->actingAs($this->david)->get(route('memories.photos.show', [$photo, 'web']));

    // The signed link lasts an hour; the redirect may be reused for 50 minutes.
    expect($response->headers->get('Cache-Control'))->toContain('private')->toContain('max-age=3000');
});

test('redirects to a signed link to the thumbnail variant', function () {
    /** @var TestCase $this */
    $photo = photoBeforeTheReveal($this->david);

    $this->actingAs($this->david)->get(route('memories.photos.show', [$photo, 'thumbnail']))
        ->assertRedirectContains($photo->thumbnail_path);
});

test('redirects the other participant once the month has revealed', function () {
    /** @var TestCase $this */
    $photo = photoAfterTheReveal($this->david);

    $this->actingAs($this->katie)->get(route('memories.photos.show', [$photo, 'web']))
        ->assertRedirectContains($photo->web_path);
});

test('is not found for the other participant before the month reveals', function () {
    /** @var TestCase $this */
    $photo = photoBeforeTheReveal($this->david);

    $this->actingAs($this->katie)->get(route('memories.photos.show', [$photo, 'web']))->assertNotFound();
});

test('is not found for someone who is not a participant', function () {
    /** @var TestCase $this */
    $photo = photoAfterTheReveal($this->david);

    $this->actingAs(User::factory()->create())->get(route('memories.photos.show', [$photo, 'web']))->assertNotFound();
});

test('sends a guest to log in', function () {
    /** @var TestCase $this */
    $photo = photoAfterTheReveal($this->david);

    $this->get(route('memories.photos.show', [$photo, 'web']))->assertRedirect(route('login'));
});

test('is not found once the photo is removed, even for its author', function () {
    /** @var TestCase $this */
    $photo = photoBeforeTheReveal($this->david);
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
    $photo = photoBeforeTheReveal($this->david);

    $this->actingAs($this->david)->get("/memories/photos/{$photo->id}/original")->assertNotFound();
});
