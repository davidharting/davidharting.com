<?php

namespace App\Livewire;

use App\Jobs\ProcessSpikePhoto;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Facades\GenerateSignedUploadUrlFacade;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\GenerateSignedUploadUrl;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Throwaway spike for the "Spike the photo pipeline" ticket (#249).
 * Never merge: it exists to exercise a PR preview from an iPhone.
 */
class PhotoSpike extends Component
{
    use WithFileUploads;

    /** Sign Livewire's presigned PUT with `ACL: private` (Livewire's default) instead of no ACL. */
    public bool $keepAcl = false;

    /** @var array<int, TemporaryUploadedFile> */
    public array $photos = [];

    public function boot(): void
    {
        $this->swapSigner();
    }

    public function hydrate(): void
    {
        $this->swapSigner();
    }

    /**
     * @param  array<int, array{name: string, size: int, type: string, read: int|null}>  $browserInfo
     */
    public function process(array $browserInfo): void
    {
        foreach ($this->photos as $index => $photo) {
            $id = now()->format('Ymd-His').'-'.Str::lower(Str::random(6));
            $extension = Str::lower(pathinfo($photo->getClientOriginalName(), PATHINFO_EXTENSION)) ?: 'bin';

            $meta = [
                'id' => $id,
                'uploaded_at' => now()->toIso8601String(),
                'acl' => $this->keepAcl ? 'private' : 'none',
                'temp_disk_is_s3' => FileUploadConfiguration::isUsingS3(),
                'browser' => $browserInfo[$index] ?? null,
                'client_original_name' => $photo->getClientOriginalName(),
                'temp_mime_type' => $photo->getMimeType(),
                'temp_size' => $photo->getSize(),
            ];

            $path = $photo->storeAs("spike/{$id}", "original.{$extension}", 'private');
            $meta['original_path'] = $path;

            Storage::disk('private')->put("spike/{$id}/meta.json", json_encode($meta, JSON_PRETTY_PRINT));

            ProcessSpikePhoto::dispatch($id, $path);
        }

        $this->photos = [];
    }

    public function render(): View
    {
        $disk = Storage::disk('private');

        $entries = collect($disk->directories('spike'))
            ->sortDesc()
            ->map(function (string $directory) use ($disk): array {
                $meta = json_decode($disk->get("{$directory}/meta.json") ?? '[]', true) ?? [];
                $result = $disk->exists("{$directory}/result.json")
                    ? json_decode($disk->get("{$directory}/result.json"), true)
                    : null;

                $url = fn (string $file): ?string => $disk->exists("{$directory}/{$file}")
                    ? $disk->temporaryUrl("{$directory}/{$file}", now()->addMinutes(10))
                    : null;

                return [
                    'meta' => $meta,
                    'result' => $result,
                    'thumb_url' => $url('thumb.jpg'),
                    'web_url' => $url('web.jpg'),
                    'original_url' => isset($meta['original_path'])
                        ? $disk->temporaryUrl($meta['original_path'], now()->addMinutes(10))
                        : null,
                ];
            })
            ->values();

        return view('livewire.photo-spike', [
            'entries' => $entries,
            'isProcessing' => $entries->contains(fn (array $entry): bool => $entry['result'] === null),
        ]);
    }

    private function swapSigner(): void
    {
        GenerateSignedUploadUrlFacade::swap($this->keepAcl
            ? new GenerateSignedUploadUrl
            : new class extends GenerateSignedUploadUrl
            {
                public function forS3($file, $visibility = 'private')
                {
                    return parent::forS3($file, null);
                }
            });
    }
}
