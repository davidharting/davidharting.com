<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Upclick;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

use App\Models\User;
use App\Models\Upclick as UpclickModel;

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
        $user = User::factory()->create();
        $stranger = User::factory()->create();

        UpclickModel::factory()->count(25)->create(); // Anonymous clicks
        UpclickModel::factory()->count(5)->create(['user_id' => $stranger->id]); // Other user's clicks
        UpclickModel::factory()->count(20)->create(['user_id' => $user->id]); // Test user's clicks


        Livewire::actingAs($user)
            ->test(Upclick::class)
            ->assertSeeHtmlInOrder(['Total Clicks', '50', 'Your Clicks', '20'])
            ->call('click')
            ->assertSeeHtmlInOrder(['Total Clicks', '51', 'Your Clicks', '21']);
    }
}
