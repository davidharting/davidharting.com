<?php

namespace App\Console\Commands\Memories;

use App\Actions\MemoryPhotos\AddMemoryPhoto;
use App\Enum\MemoryPhotoStatus;
use App\Models\Memory;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;

/**
 * PROTOTYPE (#251): push a real photo through the hand-rolled pipeline without an editor UI.
 */
class AddMemoryPhotoCommand extends Command
{
    protected $signature = 'memories:add-photo
            {email : The user whose memory gets the photo}
            {file : Path to a JPEG, PNG, WebP or HEIC file}
            {--caption= : Optional caption}';

    protected $description = 'PROTOTYPE: add a photo to the user\'s memory for last month and run the photo pipeline';

    public function handle(AddMemoryPhoto $addMemoryPhoto): int
    {
        $path = $this->argument('file');
        if (! is_file($path)) {
            $this->error("No file at {$path}");

            return self::FAILURE;
        }

        $user = User::query()->where('email', $this->argument('email'))->firstOrFail();
        $memory = Memory::query()->firstOrCreate([
            'user_id' => $user->id,
            'month' => now()->startOfMonth()->subMonth()->toDateString(),
        ]);

        // A copy, because storing an UploadedFile moves it.
        $copy = tempnam(sys_get_temp_dir(), 'memory-photo-');
        copy($path, $copy);
        $file = new UploadedFile($copy, basename($path), null, null, true);

        $photo = $addMemoryPhoto->handle($memory, $file, $this->option('caption'))->refresh();

        $this->table(['Field', 'Value'], [
            ['memory', "#{$memory->id} ({$memory->month->format('F Y')})"],
            ['photo', "#{$photo->id}, position {$photo->position}"],
            ['status', $photo->status->value],
            ['web size', $photo->width ? "{$photo->width}×{$photo->height}" : '—'],
            ['original', $photo->path('original')],
            ['failure', $photo->failure ?? '—'],
        ]);

        if ($photo->status === MemoryPhotoStatus::PROCESSING) {
            $this->info('Processing is queued. Run `php artisan queue:work --stop-when-empty`, then load a URL below while logged in as this user.');
        }

        foreach (['original', 'web', 'thumb'] as $variant) {
            $this->line("{$variant}: ".route('memory-photos.show', [$photo, $variant]));
        }

        return self::SUCCESS;
    }
}
