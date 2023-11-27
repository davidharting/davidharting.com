<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Scorecard>
 */
class ScorecardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => Str::title(Str::limit($this->faker->words(random_int(1, 10), true), 300)),
            'description' => $this->faker->sentences(random_int(0, 5), true),
        ];
    }
}
