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
            'markdown_content' => $this->faker->paragraphs(asText: true),
            'visible' => $this->faker->boolean(85),
            'published_at' => $this->faker->dateTimeBetween('-10 year', 'now', $timezone = 'EST'),
        ];
    }

    public function leadOnly(): self
    {
        return $this->state([
            'title' => null,
            'markdown_content' => null,
        ]);
    }

    public function noLead(): self
    {
        return $this->state([
            'lead' => null,
        ]);
    }

    public function contentOnly(): self
    {
        return $this->state([
            'title' => null,
            'lead' => null,
        ]);
    }
}
