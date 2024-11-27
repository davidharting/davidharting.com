<?php

namespace Database\Factories;

use App\Models\Media;
use App\Models\MediaEventType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Lottery;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MediaEvent>
 */
class MediaEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'media_event_type_id' => MediaEventType::all()->random()->id,
            'media_id' => Media::factory(),
            'occurred_at' => $this->faker->dateTimeThisDecade(),
            'comment' => Lottery::odds(1, 5)
                ->winner(fn () => $this->faker->sentence(random_int(1, 5)))
                ->loser(null),
        ];
    }
}
