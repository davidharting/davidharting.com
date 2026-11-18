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
     * Practised until FSRS expects David to remember it for weeks.
     */
    public function mastered(): static
    {
        return $this->practised(stability: 30, difficulty: 4);
    }

    /**
     * Practised, but hard for David, and not yet mastered.
     */
    public function troublesome(): static
    {
        return $this->practised(stability: 3, difficulty: 8);
    }

    /**
     * Practised, neither mastered nor troublesome.
     */
    public function learning(): static
    {
        return $this->practised(stability: 3, difficulty: 4);
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
