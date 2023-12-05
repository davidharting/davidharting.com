<?php

namespace Tests\Feature\Livewire\Scorecards;

use App\Livewire\Scorecards\Detail;
use App\Models\Player;
use App\Models\Score;
use App\Models\Scorecard;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DetailTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function renders_successfully()
    {
        Livewire::test(Detail::class)
            ->assertStatus(200);
    }

    public function test_two_players_three_rounds()
    {
        $scorecard = Scorecard::factory()->createOne(['title' => 'Conkers']);
        $frodo = Player::factory()->createOne(['name' => 'Frodo', 'scorecard_id' => $scorecard->id]);
        $sam = Player::factory()->createOne(['name' => 'Sam', 'scorecard_id' => $scorecard->id]);

        $frodo->scores()->createMany([
            ['round' => 1, 'score' => 111],
            ['round' => 2, 'score' => 333],
            ['round' => 3, 'score' => 555],
        ]);
        $sam->scores()->createMany([
            ['round' => 1, 'score' => 222],
            ['round' => 2, 'score' => 444],
            ['round' => 3, 'score' => 666],
        ]);

        $scores = Score::whereIn('player_id', function (Builder $query) use ($scorecard) {
            $query->select('player_id')->from('scorecards')->where('id', '=', $scorecard->id);
        });

        $player_ids = Player::where('scorecard_id', $scorecard->id)->select('id')->get();

        Livewire::test(Detail::class, ['scorecard' => $scorecard])
            ->assertSeeHtmlInOrder(['Frodo', 'Sam', 111, 222, 333, 444, 555, 666]);
    }
}
