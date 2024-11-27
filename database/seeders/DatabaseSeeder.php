<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\Creator;
use App\Models\Media;
use App\Models\MediaType;
use App\Models\Note;
use App\Models\Player;
use App\Models\Score;
use App\Models\Scorecard;
use App\Models\Upclick;
use App\Models\User;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! App::environment('local')) {
            throw new Exception('This seeder can only be run in the local environment');
        }
        $admin = User::factory()->create([
            'name' => 'Adam Min',
            'email' => 'admin@example.com',
            'is_admin' => true,
        ]);

        $frodo = User::factory()->create([
            'name' => 'Frodo Baggins',
            'email' => 'frodo@example.com',
        ]);

        User::factory(10)->create();

        // Anonymous clicks
        Upclick::factory(500)->create();
        // Frodos clicks
        Upclick::factory(200)->create(['user_id' => $frodo->id]);

        Scorecard::factory(20)->addPlayers()->create();

        $conkers = Scorecard::factory()->makeOne([
            'title' => 'Conkers',
        ]);

        $conkers->save();

        $conkers->players()->saveMany([
            Player::factory()->makeOne([
                'name' => 'Frodo Baggins',
            ]),
            Player::factory()->makeOne([
                'name' => 'Samwise Gamgee',
            ]),
            Player::factory()->makeOne([
                'name' => 'Peregrin Took',
            ]),
            Player::factory()->makeOne([
                'name' => 'Meriadoc Brandybuck',
            ]),
        ]);

        for ($round = 1; $round <= 10; $round++) {
            $conkers->players->each(function (Player $player) use ($round) {
                $player->scores()->save(
                    Score::factory()->makeOne([
                        'round' => $round,
                    ])
                );
            });
        }

        Note::factory(375)->create();

        $bookMediaType = MediaType::where('name', 'book')->first();

        $authors = Creator::factory(500)->create();
        $authors->each(fn (Creator $author) => Media::factory(random_int(1, 6))->hasEvents(2)->create(
            ['creator_id' => $author, 'media_type_id' => $bookMediaType]
        ));
    }
}
