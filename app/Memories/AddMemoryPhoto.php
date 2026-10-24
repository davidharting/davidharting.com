<?php

namespace App\Memories;

use App\Jobs\ProcessMemoryPhoto;
use App\Models\Memory;
use App\Models\MemoryPhoto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\Mime\MimeTypes;

/**
 * Adds an uploaded photo to the end of a memory and queues its processing.
 *
 * This class performs no authorization. The caller is expected to have
 * checked that the author may write the memory.
 */
final class AddMemoryPhoto
{
    /**
     * The most photos a memory can have. Removed photos don't count.
     */
    public const int MAX_PHOTOS = 10;

    /**
     * Move an upload that is already on the private disk into a directory of
     * its own, record it as the memory's last photo, and queue processing.
     * The memory is locked while it is counted, so two uploads at once can't
     * both take the tenth place.
     *
     * @param  string  $uploadedPath  Where the upload is on the private disk, such as Livewire's temporary upload directory.
     *
     * @throws ValidationException when the memory already has the most photos it can have.
     */
    public function handle(Memory $memory, string $uploadedPath): MemoryPhoto
    {
        $disk = Storage::disk('private');

        if (! $disk->exists($uploadedPath)) {
            throw new RuntimeException("There is no upload at {$uploadedPath}.");
        }

        $mimeType = $disk->mimeType($uploadedPath);
        $bytes = $disk->size($uploadedPath);
        $extension = MimeTypes::getDefault()->getExtensions($mimeType)[0] ?? 'bin';

        $photo = DB::transaction(function () use ($disk, $memory, $uploadedPath, $mimeType, $bytes, $extension): MemoryPhoto {
            Memory::query()->whereKey($memory->id)->lockForUpdate()->sole();

            $photos = MemoryPhoto::query()->where('memory_id', $memory->id);

            if ($photos->clone()->count() >= self::MAX_PHOTOS) {
                throw ValidationException::withMessages([
                    'photo' => 'A memory can have up to '.self::MAX_PHOTOS.' photos.',
                ]);
            }

            $originalPath = 'memories/photos/'.Str::ulid()->toBase32()."/original.{$extension}";

            if (! $disk->move($uploadedPath, $originalPath)) {
                throw new RuntimeException("Couldn't move {$uploadedPath} to {$originalPath}.");
            }

            return MemoryPhoto::query()->create([
                'memory_id' => $memory->id,
                'position' => ($photos->clone()->max('position') ?? -1) + 1,
                'original_path' => $originalPath,
                'original_mime_type' => $mimeType,
                'original_bytes' => $bytes,
            ]);
        });

        ProcessMemoryPhoto::dispatch($photo);

        return $photo;
    }
}
