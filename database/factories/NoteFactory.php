<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Note>
 */
class NoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
            'lead' => $this->faker->sentences(asText: true),
            'content' => $this->faker->paragraphs(asText: true),
            'hidden' => $this->faker->boolean(15),
            'published_at' => $this->faker->dateTimeBetween('-10 year', 'now', $timezone = 'EST'),
        ];
    }
}
