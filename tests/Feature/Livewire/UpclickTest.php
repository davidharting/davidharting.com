<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Upclick;
use App\Models\Upclick as UpclickModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UpclickTest extends TestCase
{
    use RefreshDatabase;

    public function test_clicking_updates_total_count()
    {
        Livewire::test(Upclick::class)
            ->assertSeeHtmlInOrder(['Total Clicks', '0'])
            ->call('click')
            ->assertSeeHtmlInOrder(['Total Clicks', '1']);
    }

    public function test_authenticated_user()
    {
        UpclickModel::factory()->count(25)->create(); // Anonymous clicks
        User::factory()->has(UpclickModel::factory()->count(5))->create(); // Distractor: Another user also has clicks

        $user = User::factory()->has(UpclickModel::factory()->count(20))->create();

        Livewire::actingAs($user)
            ->test(Upclick::class)
            ->assertSeeHtmlInOrder(['Total Clicks', '50', 'Your Clicks', '20'])
            ->call('click')
            ->assertSeeHtmlInOrder(['Total Clicks', '51', 'Your Clicks', '21']);
    }
}
