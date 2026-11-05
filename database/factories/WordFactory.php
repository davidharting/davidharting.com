<?php

namespace Database\Factories;

use App\Models\Word;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Word>
 */
class WordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'word' => $this->faker->unique()->word(),
            'senses' => [
                [
                    'part_of_speech' => 'adjective',
                    'definition' => $this->faker->sentence(),
                    'examples' => [$this->faker->sentence()],
                ],
            ],
            'short_definition' => $this->faker->sentence(),
        ];
    }
}
