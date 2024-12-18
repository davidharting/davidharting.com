<?php

use App\Livewire\Media\Backlog;
use App\Models\Media;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

it('smoke test', function () {
    Livewire::test(Backlog::class)
        ->assertStatus(200)
        ->assertSee('No backlog items');
});

it('renders one', function () {
    Media::factory(['created_at' => Carbon::parse('2023-01-06')])->book()->create();

    Livewire::test(Backlog::class)
        ->assertViewHas('items', function ($posts) {

            return count($posts) == 1;
        })
        ->assertStatus(200);
});
