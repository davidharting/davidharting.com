<?php

namespace Database\Factories;

use App\Memories\MemoryEmailKind;
use App\Models\MemoryEmail;
use App\Models\Month;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemoryEmail>
 */
class MemoryEmailFactory extends Factory
{
    /**
     * A month open reminder about the latest open month, claimed but not sent yet.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'month_id' => Month::latestOpen()->toDateString(),
            'kind' => MemoryEmailKind::MonthOpened,
        ];
    }

    public function sent(): static
    {
        return $this->state(['sent_at' => now()]);
    }

    public function failed(): static
    {
        return $this->state(['failed_at' => now(), 'failure' => 'The mail provider was down.']);
    }
}
