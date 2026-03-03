<?php

use App\Models\User;
use Tests\TestCase;

test('filament admin panel shows link to main website', function () {
    /** @var TestCase $this */
    $user = User::factory()->admin()->create();
    $response = $this->actingAs($user)->get('/admin');
    $response->assertStatus(200);
    $response->assertSeeText('davidharting.com');
    $response->assertSee(route('home'));
});
