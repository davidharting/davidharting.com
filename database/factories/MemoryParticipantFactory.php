<?php

namespace Database\Factories;

use App\Models\MemoryParticipant;
use App\Models\User;
use App\Support\CalendarMonth;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemoryParticipant>
 */
class MemoryParticipantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
        ];
    }

    /**
     * Joined in the middle of the given month.
     */
    public function joinedIn(CalendarMonth $month): static
    {
        return $this->state(fn () => ['created_at' => $month->at(day: 15, hour: 12)]);
    }
}
