<?php

namespace Database\Factories;

use App\Fsrs\CardSchedule;
use App\Fsrs\CardState;
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
            'short_definition' => $this->faker->sentence(),
            'full_definition' => "**adjective**\n\n1. ".$this->faker->sentence()."\n   - _".$this->faker->sentence().'_',
        ];
    }

    /**
     * Practised until FSRS expects David to remember it for three weeks or
     * more: stability of 21 days to a year, at any difficulty.
     */
    public function mastered(): static
    {
        return $this->practised(
            stability: $this->faker->randomFloat(2, 21, 365),
            difficulty: $this->faker->randomFloat(2, 1, 10),
        );
    }

    /**
     * Practised, not yet mastered, and hard for David: stability under three
     * weeks and difficulty 7 or more.
     */
    public function troublesome(): static
    {
        return $this->practised(
            stability: $this->faker->randomFloat(2, 0.1, 20.9),
            difficulty: $this->faker->randomFloat(2, 7, 10),
        );
    }

    /**
     * Practised, neither mastered nor troublesome: stability under three
     * weeks and difficulty under 7.
     */
    public function learning(): static
    {
        return $this->practised(
            stability: $this->faker->randomFloat(2, 0.1, 20.9),
            difficulty: $this->faker->randomFloat(2, 1, 6.9),
        );
    }

    /**
     * Give the word's flashcard the schedule of one last practised a day ago,
     * with the given FSRS stability (days) and difficulty (1 to 10). It sets
     * the schedule directly and logs no reviews.
     */
    public function practised(float $stability, float $difficulty): static
    {
        return $this->afterCreating(function (Word $word) use ($stability, $difficulty): void {
            $word->flashcard->schedule = new CardSchedule(
                state: CardState::Review,
                step: null,
                stability: $stability,
                difficulty: $difficulty,
                due: now()->addDays((int) ceil($stability)),
                lastReview: now()->subDay(),
            );
            $word->flashcard->save();
            // Read back the learning status Postgres generated from the schedule.
            $word->flashcard->refresh();
        });
    }
}
