<?php

namespace Database\Factories;

use App\Enum\MediaEventTypeName;
use App\Models\MediaEventType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaEventType>
 */
class MediaEventTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(MediaEventTypeName::cases()),
        ];
    }
}
