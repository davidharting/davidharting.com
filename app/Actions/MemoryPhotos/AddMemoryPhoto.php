<?php

namespace App\Actions\MemoryPhotos;

use App\Enum\MemoryPhotoStatus;
use App\Jobs\ProcessMemoryPhoto;
use App\Models\Memory;
use App\Models\MemoryPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * PROTOTYPE (#251), variant A: keep the original privately, then queue the web and thumbnail copies.
 *
 * In the real editor the file arrives as a Livewire temporary upload already on R2, so storing it is an R2 copy.
 */
class AddMemoryPhoto
{
    public function handle(Memory $memory, UploadedFile $file, ?string $caption = null): MemoryPhoto
    {
        $photo = DB::transaction(function () use ($memory, $file, $caption): MemoryPhoto {
            // Lock the memory so two parallel uploads can't both take the tenth slot.
            Memory::query()->whereKey($memory->id)->lockForUpdate()->first();

            if ($memory->photos()->count() >= Memory::MAX_PHOTOS) {
                throw ValidationException::withMessages(['photo' => 'A memory can have at most '.Memory::MAX_PHOTOS.' photos.']);
            }

            return $memory->photos()->create([
                'position' => ($memory->photos()->max('position') ?? -1) + 1,
                'caption' => $caption,
                'original_extension' => strtolower($file->extension()),
                'original_mime_type' => $file->getMimeType(),
                'original_size' => $file->getSize(),
                'status' => MemoryPhotoStatus::PROCESSING,
            ]);
        });

        Storage::disk('private')->putFileAs($photo->directory(), $file, basename($photo->path('original')));

        ProcessMemoryPhoto::dispatch($photo);

        return $photo;
    }
}
