<?php

use App\Models\Media;
use App\Models\MediaEvent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\TestCase;

test('403 when not logged in', function () {
    /** @var TestCase $this */
    $media = Media::factory()->create();

    $response = $this->get('/media/'.$media->id);

    $response->assertStatus(403);
});

test('403 for non-admin users', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();
    $media = Media::factory()->create();

    $response = $this->actingAs($user)->get('/media/'.$media->id);

    $response->assertStatus(403);
});

test('404 when media does not exist', function () {
    /** @var TestCase $this */
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get('/media/99999');

    $response->assertNotFound();
});

test('admin can view media detail page', function () {
    /** @var TestCase $this */
    $admin = User::factory()->admin()->create();
    $media = Media::factory()->create([
        'title' => 'Test Media Title',
        'year' => 2024,
        'note' => 'This is a test note',
    ]);

    $response = $this->actingAs($admin)->get('/media/'.$media->id);

    $response->assertSuccessful();
    $response->assertSeeText('Test Media Title');
    $response->assertSeeText('2024');
    $response->assertSeeText('This is a test note');
    $response->assertSeeText($media->creator->name);
    $response->assertSeeText($media->mediaType->name->value);
});

test('timeline shows added event from media created_at', function () {
    /** @var TestCase $this */
    $admin = User::factory()->admin()->create();
    $media = Media::factory()->create([
        'created_at' => Carbon::parse('2024-01-15'),
    ]);

    $response = $this->actingAs($admin)->get('/media/'.$media->id);

    $response->assertSuccessful();
    $response->assertSeeText('Added');
    $response->assertSeeText('Jan 15, 2024');
});

test('timeline shows all event types sorted oldest-first with dates and comments', function () {
    /** @var TestCase $this */
    $admin = User::factory()->admin()->create();
    $media = Media::factory()->create([
        'created_at' => Carbon::parse('2024-01-01'),
    ]);

    MediaEvent::factory()->started()->create([
        'media_id' => $media->id,
        'occurred_at' => Carbon::parse('2024-02-15'),
    ]);

    MediaEvent::factory()->comment('Great so far!')->create([
        'media_id' => $media->id,
        'occurred_at' => Carbon::parse('2024-02-20'),
    ]);

    MediaEvent::factory()->finished()->create([
        'media_id' => $media->id,
        'occurred_at' => Carbon::parse('2024-03-01'),
    ]);

    $response = $this->actingAs($admin)->get('/media/'.$media->id);

    $response->assertSuccessful();
    $response->assertSeeTextInOrder([
        'Jan 1, 2024',
        'Added',
        'Feb 15, 2024',
        'Started',
        'Feb 20, 2024',
        'Comment',
        'Great so far!',
        'Mar 1, 2024',
        'Finished',
    ]);
});

test('404 for non-numeric media ID', function (string $invalidId) {
    /** @var TestCase $this */
    $response = $this->get('/media/'.$invalidId);

    $response->assertNotFound();
})->with([
    '.env',
    '../etc/passwd',
    '<script>alert(1)</script>',
    'random-string',
    'abc',
]);

test('page includes back link to media index', function () {
    /** @var TestCase $this */
    $admin = User::factory()->admin()->create();
    $media = Media::factory()->create();

    $response = $this->actingAs($admin)->get('/media/'.$media->id);

    $response->assertSuccessful();
    $response->assertSeeText('Back to media log');
    $response->assertSee(route('media.index'));
});
