<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

test('requires auth', function () {
    /** @var TestCase $this */
    $response = $this->get('/fileshare/create');

    $response->assertredirect('/login');
});

test('happy path', function () {
    /** @var TestCase $this */
    $user = User::factory()->createOne();

    $create = $this->actingAs($user)->get('/fileshare/create');

    $create->assertStatus(200);
    $create->assertSeeInOrder(['Upload']);

    $file = UploadedFile::fake()->create('document.pdf', 128);

    $store = $this->actingAs($user)->post('/fileshare', [
        '_token' => csrf_token(),
        'file' => $file,
    ]);

    $store->assertStatus(302);
});
