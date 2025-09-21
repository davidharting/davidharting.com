<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

test('requires auth', function () {
    /** @var TestCase $this */
    $response = $this->get('/fileshare/the-path.txt');
    $response->assertRedirect('/login');
});

test('happy path', function () {
    /** @var TestCase $this */
    Storage::fake();

    Storage::put('the-path.txt', 'contents');

    $response = $this->actingAs(User::factory()->createOne())->get('/fileshare/the-path.txt');
    $response->assertStatus(200);
    $response->assertSeeInOrder(['Size', '8']);
});
