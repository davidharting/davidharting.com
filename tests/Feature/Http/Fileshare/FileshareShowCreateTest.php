<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

test('requires auth', function () {
    /** @var TestCase $this */
    $response = $this->get('/fileshare/create');

    $response->assertredirect('/login');
});

test('store rejects missing file', function () {
    /** @var TestCase $this */
    $user = User::factory()->createOne();

    $response = $this->actingAs($user)->post('/fileshare', [
        '_token' => csrf_token(),
    ]);

    $response->assertSessionHasErrors('file');
});

test('store rejects oversized file', function () {
    /** @var TestCase $this */
    $user = User::factory()->createOne();

    $file = UploadedFile::fake()->create('big.pdf', 25601); // 1KB over 25MB limit

    $response = $this->actingAs($user)->post('/fileshare', [
        '_token' => csrf_token(),
        'file' => $file,
    ]);

    $response->assertSessionHasErrors('file');
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
