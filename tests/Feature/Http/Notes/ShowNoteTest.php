<?php

use App\Models\Note;
use Carbon\Carbon;
use Tests\TestCase;

test('404 if note not found', function () {
    /** @var TestCase $this */
    $response = $this->get('/notes/1');
    $response->assertNotFound();
});

test('404 if note not visible', function() {
    /** @var TestCase $this */
    $note = Note::factory()->createOne([ 'visible' => false ]);
    $response = $this->get('/notes/' . $note->id);
    $response->assertNotFound();
});


test('shows a note', function() {
    /** @var TestCase $this */
    $note = Note::factory()->createOne(['visible' => true, 'content' => 'hello there',
        'created_at' => Carbon::create(2000, 02, 01)]);
    $response = $this->get('/notes/' . $note->id);
    $response->assertSuccessful();
    $response->assertSeeInOrder(['hello there', '2000', 'February']);
});
