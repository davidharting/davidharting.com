<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Upclick;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Livewire\Livewire;
use Tests\TestCase;

class UpclickTest extends TestCase
{
    /** @test */
    public function renders_successfully()
    {
        Livewire::test(Upclick::class)
            ->assertStatus(200);
    }
}
