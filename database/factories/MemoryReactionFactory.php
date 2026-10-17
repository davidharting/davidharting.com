<?php

namespace Database\Factories;

use App\Models\Memory;
use App\Models\MemoryReaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemoryReaction>
 */
class MemoryReactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'memory_id' => Memory::factory(),
            'user_id' => User::factory(),
            'emoji' => $this->faker->randomElement(['❤️', '😂', '🥹', '🎉', '😍', '👏', '🙌', '😭']),
        ];
    }
}
