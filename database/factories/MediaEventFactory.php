<?php

namespace Database\Factories;

use App\Enum\MediaEventTypeName;
use App\Models\Media;
use App\Models\MediaEventType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
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
                ->loser(fn () => null),
        ];
    }

    public function at(Carbon $occurred_at): MediaEventFactory
    {
        return $this->state(['occurred_at' => $occurred_at]);
    }

    public function finished(): MediaEventFactory
    {
        return $this->state([
            'media_event_type_id' => MediaEventType::where('name', MediaEventTypeName::FINISHED)->first()->id,
        ]);
    }

    public function started(): MediaEventFactory
    {
        return $this->state([
            'media_event_type_id' => MediaEventType::where('name', MediaEventTypeName::STARTED)->first()->id,
        ]);
    }

    public function abandoned(): MediaEventFactory
    {
        return $this->state([
            'media_event_type_id' => MediaEventType::where('name', MediaEventTypeName::ABANDONED)->first()->id,
        ]);
    }

    public function comment(string $text): MediaEventFactory
    {
        return $this->state([
            'media_event_type_id' => MediaEventType::where('name', MediaEventTypeName::COMMENT)->first()->id,
            'comment' => $text,
        ]);
    }
}
