<?php

namespace Tests\Feature\Livewire\Media;

// This test could be moved.
// It used to test a full-page livewire component.
// Now the route just redirects

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogbookTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirects()
    {
        $this->get('/media/log?year=2024')
            ->assertRedirect('/media?year=2024');
    }
}
