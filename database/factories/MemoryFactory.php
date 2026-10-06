<?php

namespace Database\Factories;

use App\Models\Memory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * PROTOTYPE (#251)
 *
 * @extends Factory<Memory>
 */
class MemoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'month' => now()->startOfMonth()->subMonth(),
            'body' => fake()->paragraphs(2, true),
        ];
    }
}
