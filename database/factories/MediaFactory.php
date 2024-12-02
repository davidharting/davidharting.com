<?php

namespace Database\Factories;

use App\Models\Creator;
use App\Models\MediaType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Media>
 */
class MediaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $mediaTypes = MediaType::all(['id']);

        return [
            'year' => $this->faker->year,
            'title' => Str::title($this->faker->words($this->faker->numberBetween(1, 5), true)),
            'note' => $this->faker->randomElement([
                null,
                '',
                $this->faker->paragraph($this->faker->numberBetween(1, 5)),
            ]),
            'media_type_id' => $this->faker->randomElement($mediaTypes)->id,
            'creator_id' => Creator::factory(),
        ];
    }

    public function book(): MediaFactory
    {
        return $this->state([
            'media_type_id' => MediaType::where('name', 'book')->first()->id,
        ]);
    }
}
