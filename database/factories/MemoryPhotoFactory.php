<?php

namespace Database\Factories;

use App\Memories\PhotoVariant;
use App\Models\Memory;
use App\Models\MemoryPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MemoryPhoto>
 */
class MemoryPhotoFactory extends Factory
{
    /**
     * A JPEG straight off a phone, still processing.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'memory_id' => Memory::factory(),
            'position' => 0,
            'caption' => null,
            'original_path' => 'memories/photos/'.Str::ulid()->toBase32().'/original.jpg',
            'original_mime_type' => 'image/jpeg',
            'original_bytes' => $this->faker->numberBetween(1_500_000, 4_500_000),
            'variants' => null,
            'failed_at' => null,
            'failure' => null,
        ];
    }

    public function captioned(?string $caption = null): static
    {
        return $this->state(['caption' => $caption ?? $this->faker->sentence()]);
    }

    /**
     * Processed into a web copy of the given size and a thumbnail that fits
     * in 512 pixels, both next to the original.
     */
    public function ready(int $width = 2048, int $height = 1536): static
    {
        return $this->state(function (array $attributes) use ($width, $height): array {
            $directory = dirname($attributes['original_path']);
            $scale = 512 / max($width, $height);

            return [
                'variants' => [
                    'web' => (new PhotoVariant("{$directory}/web.jpg", 'image/jpeg', $width, $height, 450_000))->toArray(),
                    'thumbnail' => (new PhotoVariant("{$directory}/thumbnail.jpg", 'image/jpeg', (int) round($width * $scale), (int) round($height * $scale), 40_000))->toArray(),
                ],
            ];
        });
    }

    public function failed(string $failure = 'vipsthumbnail exited with code 1'): static
    {
        return $this->state([
            'failed_at' => now(),
            'failure' => $failure,
        ]);
    }
}
