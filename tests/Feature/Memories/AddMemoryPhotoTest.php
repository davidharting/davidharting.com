<?php

use App\Jobs\ProcessMemoryPhoto;
use App\Memories\AddMemoryPhoto;
use App\Models\Memory;
use App\Models\MemoryPhoto;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\PhotoInspector;
use Tests\TestCase;

beforeEach(function () {
    Storage::fake('private');
    $this->memory = Memory::factory()->create();
    $this->upload = function (string $fixture = 'iphone.jpg'): string {
        $path = 'livewire-tmp/'.uniqid().'.'.pathinfo($fixture, PATHINFO_EXTENSION);
        Storage::disk('private')->put($path, file_get_contents(PhotoInspector::fixture($fixture)));

        return $path;
    };
});

test('moves the upload into a directory of its own as the original', function (string $fixture, string $mimeType, string $extension) {
    /** @var TestCase $this */
    Queue::fake();
    $upload = ($this->upload)($fixture);

    $photo = (new AddMemoryPhoto)->handle($this->memory, $upload);

    expect($photo->original_path)->toMatch("#^memories/photos/[0-9A-Z]{26}/original\.{$extension}$#")
        ->and($photo->original_mime_type)->toBe($mimeType)
        ->and($photo->original_bytes)->toBe(filesize(PhotoInspector::fixture($fixture)))
        ->and($photo->isProcessing())->toBeTrue();
    Storage::disk('private')->assertMissing($upload);
    Storage::disk('private')->assertExists($photo->original_path);
})->with([
    'a JPEG' => ['iphone.jpg', 'image/jpeg', 'jpg'],
    'a HEIC' => ['iphone.heic', 'image/heic', 'heic'],
]);

test('queues processing for the photo', function () {
    /** @var TestCase $this */
    Queue::fake();

    $photo = (new AddMemoryPhoto)->handle($this->memory, ($this->upload)());

    Queue::assertPushed(ProcessMemoryPhoto::class, fn (ProcessMemoryPhoto $job): bool => $job->photo->is($photo));
});

test('the photo is ready once its processing has run', function () {
    /** @var TestCase $this */
    $photo = (new AddMemoryPhoto)->handle($this->memory, ($this->upload)());

    expect($photo->refresh()->isReady())->toBeTrue();
});

test('adds the photo after the memory\'s last one', function () {
    /** @var TestCase $this */
    Queue::fake();
    MemoryPhoto::factory()->for($this->memory)->create(['position' => 0]);
    MemoryPhoto::factory()->for($this->memory)->create(['position' => 3]);
    MemoryPhoto::factory()->create(['position' => 7]);

    $photo = (new AddMemoryPhoto)->handle($this->memory, ($this->upload)());

    expect($photo->position)->toBe(4);
});

test('refuses an eleventh photo and leaves the upload where it was', function () {
    /** @var TestCase $this */
    Queue::fake();
    MemoryPhoto::factory()->for($this->memory)->count(10)->sequence(fn ($sequence) => ['position' => $sequence->index])->create();
    $upload = ($this->upload)();

    expect(fn () => (new AddMemoryPhoto)->handle($this->memory, $upload))
        ->toThrow(ValidationException::class, 'A memory can have up to 10 photos.');
    expect($this->memory->photos()->count())->toBe(10);
    Storage::disk('private')->assertExists($upload);
    Queue::assertNothingPushed();
});

test('does not count removed photos toward the limit', function () {
    /** @var TestCase $this */
    Queue::fake();
    MemoryPhoto::factory()->for($this->memory)->count(10)->sequence(fn ($sequence) => ['position' => $sequence->index])->create();
    $this->memory->photos()->first()->delete();

    $photo = (new AddMemoryPhoto)->handle($this->memory, ($this->upload)());

    expect($photo->position)->toBe(10)
        ->and($this->memory->photos()->count())->toBe(10);
});
