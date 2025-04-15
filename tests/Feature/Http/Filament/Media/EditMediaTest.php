<?php

use App\Models\Media;
use App\Models\User;
use Tests\TestCase;

test('anonymous user not allowed', function () {
    /** @var TestCase $this */
    $media = Media::factory()->create();

    $response = $this->get(route('filament.admin.resources.media.edit', $media));

    $response->assertRedirect(route('filament.admin.auth.login'));
});

test('regular user not allowed', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();
    $media = Media::factory()->create();

    $response = $this->actingAs($user)->get(route('filament.admin.resources.media.edit', $media));

    $response->assertStatus(403);
});

test('Visible to admin', function () {
    /** @var TestCase $this */
    $media = Media::factory()->create(['title' => 'The Hobbit']);
    $user = User::factory()->admin()->create();

    $response = $this->actingAs($user)->get(route('filament.admin.resources.media.edit', $media));

    $response->assertStatus(200);
    $response->assertSeeText('Edit Media');
});
