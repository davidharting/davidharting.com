<?php

use App\Jobs\ProcessSpikePhoto;
use App\Livewire\PhotoSpike;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

test('only admins can open the spike page', function () {
    /** @var TestCase $this */
    $this->get('/spike/photos')->assertRedirect('/login');

    $this->actingAs(User::factory()->create())->get('/spike/photos')->assertForbidden();

    $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/spike/photos')->assertOk();
});

test('process stores the original and dispatches the vips job', function () {
    /** @var TestCase $this */
    Storage::fake('private');
    Queue::fake();
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(PhotoSpike::class)
        ->set('photos', [UploadedFile::fake()->create('IMG_0001.HEIC', 2048, 'image/heic')])
        ->call('process', [['name' => 'IMG_0001.HEIC', 'size' => 2097152, 'type' => 'image/heic']]);

    Queue::assertPushed(ProcessSpikePhoto::class, function (ProcessSpikePhoto $job): bool {
        return str_ends_with($job->originalPath, '/original.heic')
            && Storage::disk('private')->exists($job->originalPath)
            && Storage::disk('private')->exists("spike/{$job->id}/meta.json");
    });
});
