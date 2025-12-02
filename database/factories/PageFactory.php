<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Page>
 */
class PageFactory extends Factory
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
            'markdown_content' => $this->faker->paragraphs(asText: true),
            'is_published' => false,
        ];
    }

    public function published(): self
    {
        return $this->state([
            'is_published' => true,
        ]);
    }

    public function unpublished(): self
    {
        return $this->state([
            'is_published' => false,
        ]);
    }
}
