<?php

namespace Database\Factories;

use App\Models\Memory;
use App\Models\User;
use App\Support\Month;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Memory>
 */
class MemoryFactory extends Factory
{
    /**
     * A draft for the latest open month, started a day after it opened.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'month' => Month::latestOpen(),
            'body' => implode("\n\n", $this->faker->paragraphs(random_int(1, 4))),
            'lock_version' => 0,
            'created_at' => fn (array $attributes) => $this->monthOf($attributes)->opensAt()->addDay(),
            'shared_at' => null,
            'updated_at' => fn (array $attributes) => $attributes['shared_at'] ?? $attributes['created_at'],
        ];
    }

    public function forMonth(Month $month): static
    {
        return $this->state(['month' => $month]);
    }

    /**
     * Started this many days after the month opened.
     */
    public function startedDaysAfterOpening(int $days): static
    {
        return $this->state([
            'created_at' => fn (array $attributes) => $this->monthOf($attributes)->opensAt()->addDays($days),
        ]);
    }

    /**
     * Shared at the given time, or a few days after it was started.
     */
    public function shared(?CarbonImmutable $at = null): static
    {
        return $this->state([
            'shared_at' => fn (array $attributes) => $at ?? CarbonImmutable::parse($attributes['created_at'])->addDays(3),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function monthOf(array $attributes): Month
    {
        $month = $attributes['month'];

        return $month instanceof Month ? $month : Month::parse((string) $month);
    }
}
