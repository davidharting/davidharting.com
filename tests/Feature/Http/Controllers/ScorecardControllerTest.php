<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Scorecard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScorecardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_smoke_test_routes(): void
    {
        $response = $this->get('/scorecards/create');
        $response->assertOk();

        $scorecard = Scorecard::factory()->createOne();
        $response = $this->get("/scorecards/{$scorecard->id}");
        $response->assertOk();
    }
}
