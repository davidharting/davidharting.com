<?php

use App\Enum\MemoryPhotoStatus;
use App\Models\MemoryPhoto;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

beforeEach(function () {
    Storage::fake('private', ['serve' => true]);
});

test('adds a processed photo to last month\'s memory', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();

    $this->artisan('memories:add-photo', [
        'email' => $user->email,
        'file' => base_path('tests/Fixtures/photos/iphone.jpg'),
        '--caption' => 'Fireworks',
    ])->assertSuccessful()->expectsOutputToContain('ready');

    $photo = MemoryPhoto::query()->sole();
    expect($photo->status)->toBe(MemoryPhotoStatus::READY)
        ->and($photo->caption)->toBe('Fireworks')
        ->and($photo->memory->user->is($user))->toBeTrue()
        ->and($photo->memory->month->toDateString())->toBe(now()->startOfMonth()->subMonth()->toDateString())
        ->and(file_exists(base_path('tests/Fixtures/photos/iphone.jpg')))->toBeTrue();
});

test('fails on a missing file', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();

    $this->artisan('memories:add-photo', ['email' => $user->email, 'file' => '/nope.jpg'])->assertFailed();
});
