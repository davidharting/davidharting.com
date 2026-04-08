<?php

namespace Database\Factories;

use App\Enum\MediaTypeName;
use App\Models\MediaType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaType>
 */
class MediaTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(MediaTypeName::cases()),
        ];
    }
}
