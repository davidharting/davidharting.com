<?php

use App\Models\Note;
use Carbon\Carbon;
use Tests\TestCase;

test('404 if note not found', function () {
    /** @var TestCase $this */
    $response = $this->get('/notes/some-fake-slug');
    $response->assertNotFound();
});

test('404 if note not visible', function () {
    /** @var TestCase $this */
    $note = Note::factory()->createOne(['visible' => false]);
    $response = $this->get('/notes/'.$note->slug);
    $response->assertNotFound();
});

test('show', function () {
    /** @var TestCase $this */
    $note = Note::factory()->create([
        'visible' => true,
        'title' => 'A cool post',
        'lead' => 'You should read this',
        'content' => 'Captivating content',
        'published_at' => Carbon::create(2000, 02, 01),
    ]);
    $response = $this->get('/notes/'.$note->slug);
    $response->assertSuccessful();
    $response->assertSeeInOrder(['A cool post', 'You should read this', '2000', 'February', 'Captivating content']);
});
